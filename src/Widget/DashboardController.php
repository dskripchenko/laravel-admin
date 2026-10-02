<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The per-user dashboard layout: get, save and reset — where a reset deletes
 * the row and falls back to the default.
 *
 * The URL is `/api/admin/dashboard/{action}`. Nothing ties it to a particular
 * dashboard: the `dashboard_key` arrives in the payload.
 */
class DashboardController extends ApiController
{
    /**
     * Returns the current user's saved layout for a given dashboard, or null
     * when there is none — the SPA then uses the default.
     *
     * @input string $key
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DashboardLayoutResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function get(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $request->validate(['key' => ['required', 'string']]);
        $screen = $this->resolveDashboard((string) $request->input('key'), $screens);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }
        $user = $this->user();
        if ($user === null) {
            return $this->success(['layout' => null]);
        }

        $layout = DashboardLayout::query()
            ->where('dashboard_key', $request->input('key'))
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->first();

        $items = $layout?->widgets;
        if ($items !== null && $screen !== null) {
            // A widget the user may not see is not handed back, even when an
            // older layout — saved before a permission was revoked — has it.
            $items = $screen->filterLayoutItems($items);
        }

        return $this->success([
            'layout' => $items,
            'period' => $layout?->getAttribute('period'),
        ]);
    }

    /**
     * Saves the dashboard's per-user period — the "last N days" filter —
     * without touching the layout. It is persisted so that the choice survives
     * a reload.
     *
     * @input string $key
     * @input string $period
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function savePeriod(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'period' => ['required', 'string', 'max:16'],
        ]);

        $screen = $this->resolveDashboard($data['key'], $screens);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }
        $accepted = $screen?->acceptsPeriod($data['period']) ?? DashboardContext::isValidPeriod($data['period']);
        if (! $accepted) {
            throw ValidationException::withMessages([
                'period' => __('Недопустимый период: :period', ['period' => $data['period']]),
            ]);
        }

        $user = $this->user();
        if ($user === null) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $row = DashboardLayout::query()->firstOrNew([
            'dashboard_key' => $data['key'],
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
        ]);
        // The period may be saved before the layout is customized at all, and
        // widgets is NOT NULL, so a new row is seeded with an empty one.
        if ($row->getAttribute('widgets') === null) {
            $row->setAttribute('widgets', []);
        }
        $row->setAttribute('period', $data['period']);
        $row->save();

        return $this->success(['period' => $data['period']]);
    }

    /**
     * Saves the current user's layout.
     *
     * @input string $key
     * @input array $widgets
     * @input string $widgets[].slug
     * @input integer ?$widgets[].size Columns, 1..12
     * @input integer ?$widgets[].position
     * @input boolean ?$widgets[].hidden
     * @input string ?$widgets[].type Needed by user-added widgets, which carry
     *                     a key of their own rather than one from the manifest
     * @input object ?$widgets[].config The widget's own settings, if it has any
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DashboardLayoutSavedResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function save(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'widgets' => ['required', 'array'],
            'widgets.*.slug' => ['required', 'string'],
            'widgets.*.size' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.position' => ['nullable', 'integer', 'min:0'],
            'widgets.*.hidden' => ['nullable', 'boolean'],
            // The widget's type, needed by the user-added ones, which carry a
            // custom key rather than a manifest one. The backend widget
            // ignores this field for declared widgets, but the frontend
            // renderer uses it to draw them.
            'widgets.*.type' => ['nullable', 'string'],
            // The per-widget configuration: a title, the content of a
            // markdown widget, a gauge's value and so on. The frontend
            // renderer puts it into the widget's `data`; for the backend
            // widgets it acts as an override — a new title, say.
            'widgets.*.config' => ['nullable', 'array'],
        ]);

        $screen = $this->resolveDashboard($data['key'], $screens);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }

        $user = $this->user();
        if ($user === null) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $row = DashboardLayout::query()->updateOrCreate(
            [
                'dashboard_key' => $data['key'],
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
            ],
            // A layout cannot smuggle in a widget the user may not see.
            ['widgets' => $screen?->filterLayoutItems($data['widgets']) ?? $data['widgets']],
        );

        return $this->success([
            'id' => $row->id,
            'widgets' => $row->widgets,
        ]);
    }

    /**
     * Fresh widget data for a dashboard, with the filters (the period and so
     * on) applied. The frontend calls it when the date range changes, so that
     * the widgets are recomputed without reloading the whole manifest.
     *
     * @input string $key
     * @input string ?$period `all` or a number of days such as 7d; the dashboard's default otherwise
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DashboardWidgetsResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function widgets(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'period' => ['nullable', 'string', 'max:16'],
        ]);

        $screen = $this->resolveDashboard($data['key'], $screens);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }
        if ($screen === null) {
            return $this->unknownDashboard($data['key']);
        }

        // The period reaches the widgets through their DashboardContext, and
        // the screen itself through period()/periodDays() in widgets(). An
        // unknown period falls back to the dashboard's default.
        $screen->withPeriod($data['period'] ?? $screen->defaultPeriod());

        return $this->success([
            'widgets' => $screen->compileWidgets(),
            'period' => $screen->period(),
        ]);
    }

    /**
     * Drops the customization: the row is deleted and the default returns.
     *
     * @input string $key The dashboard
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DashboardLayoutResetResponse}
     * @response 401 {UnauthenticatedErrorResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function reset(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $request->validate(['key' => ['required', 'string']]);
        $screen = $this->resolveDashboard((string) $request->input('key'), $screens);
        if ($screen instanceof JsonResponse) {
            return $screen;
        }
        $user = $this->user();
        if ($user === null) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        DashboardLayout::query()
            ->where('dashboard_key', $request->input('key'))
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->delete();

        return $this->success(['key' => $request->input('key')]);
    }

    /**
     * Finds the dashboard a key names and checks the user may open it.
     *
     * Returns the screen; null when the key is not a registered dashboard at
     * all — a host may keep a layout for a DashboardPage of its own, which has
     * no screen behind it; or the error response: 404 for a dashboard of
     * another panel, 403 for one the user has no permission for.
     */
    private function resolveDashboard(string $key, ScreenRegistry $screens): DashboardScreen|JsonResponse|null
    {
        $class = $screens->get($key);
        if ($class === null || ! is_subclass_of($class, DashboardScreen::class)) {
            return null;
        }

        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        if (! array_key_exists($key, $screens->all($panel))) {
            return $this->unknownDashboard($key);
        }

        $screen = app($class);
        if (! $screen instanceof DashboardScreen) {
            return $this->unknownDashboard($key);
        }
        if (! $screen->canAccess($this->user())) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => __('Нет доступа к дашборду'),
            ], Response::HTTP_FORBIDDEN);
        }

        return $screen;
    }

    private function unknownDashboard(string $key): JsonResponse
    {
        return $this->error([
            'errorKey' => 'unknown_dashboard',
            'message' => "Dashboard `{$key}` not registered",
        ], Response::HTTP_NOT_FOUND);
    }

    private function user(): ?Model
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }
}

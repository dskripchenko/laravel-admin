<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     */
    public function get(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string']]);
        $user = $this->user();
        if ($user === null) {
            return $this->success(['layout' => null]);
        }

        $layout = DashboardLayout::query()
            ->where('dashboard_key', $request->input('key'))
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->first();

        return $this->success([
            'layout' => $layout?->widgets,
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
     */
    public function savePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'period' => ['required', 'string', 'max:16'],
        ]);

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
     */
    public function save(Request $request): JsonResponse
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
            ['widgets' => $data['widgets']],
        );

        return $this->success([
            'id' => $row->id,
            'widgets' => $row->widgets,
        ]);
    }

    /**
     * Drops the customization: the row is deleted and the default returns.
     *
     * @input string $key
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    /**
     * Fresh widget data for a dashboard, with the filters (the period and so
     * on) applied. The frontend calls it when the date range changes, so that
     * the widgets are recomputed without reloading the whole manifest.
     *
     * @input string $key
     * @input string ?$period 7d/30d/90d/all; 30d by default
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {DashboardWidgetsResponse}
     */
    public function widgets(Request $request, ScreenRegistry $screens): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'period' => ['nullable', 'string'],
        ]);

        $screenClass = $screens->get($data['key']);
        if ($screenClass === null || ! is_subclass_of($screenClass, DashboardScreen::class)) {
            return $this->error([
                'errorKey' => 'unknown_dashboard',
                'message' => "Dashboard `{$data['key']}` not registered",
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var DashboardScreen $screen */
        $screen = app($screenClass);
        // The period is passed into the screen context, where a
        // DashboardScreen may use it in `widgets()` for a conditional
        // aggregation — see Screen::query() and $this->context(). A screen
        // that ignores it simply returns the same set of widgets.
        $screen->withPeriod($data['period'] ?? '30d');

        $widgets = [];
        foreach ($screen->widgets() as $widget) {
            if (! $widget->isVisible()) {
                continue;
            }
            $widgets[] = $widget->toArray();
        }

        return $this->success([
            'widgets' => $widgets,
            'period' => $data['period'] ?? '30d',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string']]);
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

    private function user(): ?Model
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }
}

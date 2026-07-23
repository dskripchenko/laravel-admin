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
 * Per-user dashboard layout: get/save/reset (delete row → fallback на default).
 *
 * URL: `/api/admin/dashboard/{action}`. Привязки к конкретному dashboard'у
 * нет — `dashboard_key` приходит в payload.
 */
class DashboardController extends ApiController
{
    /**
     * Получить сохранённый layout текущего пользователя для конкретного dashboard'а.
     * Если не найден — возвращает null (SPA использует default).
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
     * Сохранить per-user период дашборда (фильтр «за N дней»), не трогая
     * layout. Персистится чтобы выбор пережил перезагрузку (BL-16).
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
        // Период может сохраняться до какой-либо кастомизации layout'а —
        // widgets NOT NULL, поэтому засеваем пустым для новой записи.
        if ($row->getAttribute('widgets') === null) {
            $row->setAttribute('widgets', []);
        }
        $row->setAttribute('period', $data['period']);
        $row->save();

        return $this->success(['period' => $data['period']]);
    }

    /**
     * Сохранить layout текущего пользователя.
     *
     * @input string $key
     * @input array $widgets
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
            // Тип widget'а — нужен для user-added (custom-key, не из manifest).
            // Backend-Widget не использует это поле для declared widgets,
            // но frontend-renderer применяет его для рендера.
            'widgets.*.type' => ['nullable', 'string'],
            // Per-widget конфиг (title, content для markdown, value для gauge, ...).
            // Frontend-Renderer кладёт это в `data` widget'а; для backend-widgets
            // используется как override (например, новый title).
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
     * Сбросить кастомизацию — удалить запись, чтобы вернуться к default.
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
     * Свежие widget data для dashboard'а с применением фильтров (period, …).
     * Frontend вызывает при смене date-range, чтобы виджеты пересчитались
     * без полного reload manifest'а.
     *
     * @input string $key
     * @input string ?$period 7d/30d/90d/all (default 30d)
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
        // Прокидываем period в screen-context — DashboardScreen может
        // использовать его в `widgets()` для условной агрегации (см.
        // Screen::query()/$this->context()). Если screen не учитывает —
        // получим тот же набор виджетов.
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

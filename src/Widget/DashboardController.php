<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

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
final class DashboardController extends ApiController
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
        ]);
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
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }
}

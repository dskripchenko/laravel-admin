<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Notifications;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

/**
 * REST endpoints для notification-center в admin-shell'е.
 *
 * URL: `/api/admin/notifications/{action}`. Все actions требуют admin-guard.
 *
 * Серверная сторона хранит notifications в `notifications` (Laravel-default
 * DatabaseNotification). Каналы доставки реализуются host-проектом:
 * `via(['database', 'broadcast'])` либо custom Channel'ы.
 */
final class NotificationController extends ApiController
{
    /**
     * Список нотификаций текущего пользователя (paginated).
     *
     * @input integer ?$per_page
     * @input string ?$type  unread|read|all
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NotificationListResponse}
     */
    public function list(Request $request): JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->success(['data' => [], 'meta' => $this->emptyMeta()]);
        }

        $type = (string) $request->input('type', 'all');
        $perPage = max(1, min(
            (int) $request->input('per_page', (int) config('admin.pagination.notifications_per_page', 20)),
            (int) config('admin.pagination.max_per_page', 100),
        ));

        $query = $this->baseQuery($user);
        if ($type === 'unread') {
            $query = $query->whereNull('read_at');
        } elseif ($type === 'read') {
            $query = $query->whereNotNull('read_at');
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'data' => $paginator->getCollection()
                ->map(static fn (DatabaseNotification $n): array => self::serialize($n))
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'unread_count' => $this->unreadCount($user),
            ],
        ]);
    }

    /**
     * Только unread — для bell-badge polling'а.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NotificationUnreadResponse}
     */
    public function unread(): JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->success(['data' => [], 'count' => 0]);
        }

        $list = $this->baseQuery($user)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->success([
            'count' => $this->unreadCount($user),
            'data' => $list->map(static fn (DatabaseNotification $n): array => self::serialize($n))->all(),
        ]);
    }

    /**
     * Пометить одну нотификацию как прочитанную.
     *
     * @input string $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NotificationMarkResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string']]);
        $user = $this->user();
        if ($user === null) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], 401);
        }

        $n = $this->baseQuery($user)->whereKey($data['id'])->first();
        if ($n === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Notification not found',
            ], 404);
        }

        $n->markAsRead();

        return $this->success([
            'id' => $n->getKey(),
            'unread_count' => $this->unreadCount($user),
        ]);
    }

    /**
     * Пометить все нотификации текущего пользователя как прочитанные.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {NotificationMarkAllResponse}
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->user();
        if ($user === null) {
            return $this->success(['updated' => 0]);
        }

        $count = $this->unreadCount($user);
        $this->baseQuery($user)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success([
            'updated' => $count,
            'unread_count' => 0,
        ]);
    }

    /**
     * Удалить нотификацию.
     *
     * @input string $id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string']]);
        $user = $this->user();
        if ($user === null) {
            return $this->error(['errorKey' => 'unauthenticated', 'message' => 'Unauthenticated'], 401);
        }

        $n = $this->baseQuery($user)->whereKey($data['id'])->first();
        if ($n === null) {
            return $this->error(['errorKey' => 'not_found', 'message' => 'Notification not found'], 404);
        }
        $n->delete();

        return $this->success([
            'id' => $data['id'],
            'unread_count' => $this->unreadCount($user),
        ]);
    }

    /**
     * Базовый Eloquent builder DatabaseNotification по notifiable.
     *
     * @return \Illuminate\Database\Eloquent\Builder<DatabaseNotification>
     */
    private function baseQuery(Model $user): \Illuminate\Database\Eloquent\Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }

    private function unreadCount(Model $user): int
    {
        return $this->baseQuery($user)->whereNull('read_at')->count();
    }

    /**
     * Возвращает текущего notifiable Eloquent-юзера. Проверяет Notifiable trait.
     */
    /**
     * Проверяет существование notifications-таблицы. Default Laravel
     * migration `2014_10_12_100000_create_notifications_table` может быть
     * не запущен в host-проекте — не валим 500-ой, отвечаем как «у юзера
     * нет нотификаций».
     */
    private function tableExists(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('notifications');
    }

    private function user(): ?Model
    {
        if (! $this->tableExists()) {
            return null;
        }
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();
        if (! $user instanceof Model) {
            return null;
        }
        if (! in_array(Notifiable::class, class_uses_recursive($user::class), true)) {
            return null;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serialize(DatabaseNotification $n): array
    {
        return [
            'id' => $n->getKey(),
            'type' => (string) $n->getAttribute('type'),
            'data' => $n->getAttribute('data'),
            'read_at' => $n->getAttribute('read_at')?->toIso8601String(),
            'created_at' => $n->getAttribute('created_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function emptyMeta(): array
    {
        return [
            'page' => 1,
            'per_page' => 0,
            'total' => 0,
            'last_page' => 1,
            'unread_count' => 0,
        ];
    }
}

# API: Delayed processes

Универсальный controller для отслеживания фоновых операций. Используется SPA-интерсептором `applyAxiosInterceptor` из `dskripchenko/laravel-delayed-process`.

> Все actions, которые могут уйти в background (export, import, bulk async, ...), отвечают envelope'ом `{ payload: { delayed: { uuid, status, progress, message } } }` (см. [conventions.md](conventions.md) §4). Дальше клиент работает с этим controller'ом.

URL: `api/admin/delayed/{action}`.

---

## DelayedController

### Регистрация

```php
'delayed' => [
    'controller' => DelayedController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'status' => ['method' => ['get']],
        'cancel' => ['method' => ['post']],
        'list'   => ['method' => ['get']],
    ],
],
```

---

## Действия

### `delayed.status`

```php
/**
 * Получить статус одного или нескольких процессов одним запросом.
 * Поддерживает batch до 50 uuid'ов через повторение параметра uuid[].
 *
 * @input array $uuid Список UUID процессов.
 * @input string(uuid) $uuid[]
 *
 * @output object $payload
 * @output array  $payload.processes Список DelayedProcessStatus.
 * @output string(uuid) $payload.processes[].uuid
 * @output string $payload.processes[].status new|running|done|failed|cancelled|expired.
 * @output integer $payload.processes[].progress 0-100.
 * @output string ?$payload.processes[].message
 * @output string(date-time) ?$payload.processes[].started_at
 * @output string(date-time) ?$payload.processes[].finished_at
 * @output integer ?$payload.processes[].duration_ms
 * @output integer $payload.processes[].attempts
 * @output mixed   ?$payload.processes[].data Финальный payload, когда status=done.
 * @output object  ?$payload.processes[].error
 * @output string  $payload.processes[].error.class
 * @output string  $payload.processes[].error.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DelayedStatusResponse}
 * @response 404 {NotFoundErrorResponse} UUID не найден или не принадлежит юзеру.
 * @response 403 {ForbiddenErrorResponse} Видны только свои процессы (либо admin.system.delayed.view_any).
 */
public function status(Request $request): JsonResponse;
```

### `delayed.cancel`

```php
/**
 * Отменить процесс (если handler поддерживает Cancellable-trait).
 *
 * @input string(uuid) $uuid
 *
 * @output object $payload
 * @output string $payload.status cancelled|finishing.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DelayedCancelResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 409 {CannotCancelResponse} Процесс уже в финальном статусе или не cancellable.
 * @response 403 {ForbiddenErrorResponse}
 */
public function cancel(Request $request): JsonResponse;
```

### `delayed.list`

```php
/**
 * Список процессов текущего пользователя (paginated). Полезно для debug-страницы.
 *
 * @input integer ?$page
 * @input integer ?$per_page
 * @input string  ?$filter_status new|running|done|failed|cancelled|expired.
 * @input string  ?$filter_command FQCN handler-класса.
 * @input string(date-time) ?$filter_started_at_from
 * @input string(date-time) ?$filter_started_at_to
 *
 * @output object $payload
 * @output array  $payload.data Список DelayedProcessStatus.
 * @output object $payload.meta Пагинация.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DelayedListResponse}
 */
public function list(Request $request): JsonResponse;
```

---

## Polling-стратегия (для справки SPA-разработчика)

`applyAxiosInterceptor` из `delayed-process` сам реализует poll. Базовая логика:

1. Если ответ содержит `payload.delayed.uuid` — interceptor подменяет промис на новый, который резолвится после `done`/`failed`.
2. Опрос каждые 1–3 секунды.
3. Поддержка `BatchPoller` — несколько активных uuid'ов опрашиваются одним запросом `delayed.status` с массивом uuid.
4. При `failed` промис отклоняется с error-объектом.
5. При `cancelled` — промис отклоняется с `errorKey: 'cancelled'`.
6. При `expired` (зависший процесс убит `delayed:expire`) — отклоняется с `errorKey: 'expired'`.

---

## Конфигурация process-классов

Per-process параметры (queue, timeout, attempts) задаются в `config/delayed-process.php → allowed_entities` для каждого process-класса. admin auto-merge'ит свои process-классы (см. ARCHITECTURE.md п.5.9):

- `ImportProcess` (импорт-мастер).
- `ExportProcess` (CSV/XLSX/PDF экспорт).
- `BulkActionAsyncProcess` (массовые async-actions).
- `MediaVariantsProcess` (если установлен `laravel-admin-media`).
- ... всё, что зарегистрировано как `Action\Async`.

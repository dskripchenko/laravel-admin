# API: Resource Actions (bulk и single)

Bulk-actions, single-record actions, async через delayed-process. Все они — actions того же контроллера, что Resource (`api/admin/{resource_slug}/{action}`). Отдельных контроллеров для actions нет — есть три универсальных метода: `bulkAction`, `singleAction`, `actionParameters`.

> Конвенции — [conventions.md](conventions.md). Resource CRUD — [resources.md](resources.md). Восстановление soft-deleted (`restore`/`forceDelete`/`replicate`) — отдельные actions, см. [resources.md](resources.md).

---

## `users.bulkAction`

```php
/**
 * Выполнить bulk-action на выбранных записях.
 *
 * @input string $action Имя action (зарегистрирован в Resource::actions()).
 * @input array  ?$ids Список ID. Взаимоисключающе с filters.
 * @input integer ?$ids[]
 * @input array  ?$filters Если ids не передан — выполнить на всех записях, удовлетворяющих фильтрам.
 * @input string  $filters[].column
 * @input string  $filters[].operator
 * @input mixed   ?$filters[].value
 * @input object  ?$parameters Параметры action'а (если Action::parameters() задан — модалка с формой).
 *
 * @header string ?$X-Idempotency-Key Рекомендуется.
 *
 * @output object $payload Синхронный ответ.
 * @output integer $payload.affected Сколько записей затронуто.
 * @output string  $payload.message
 * @output boolean $payload.refresh Нужно ли SPA перезагрузить таблицу.
 * @output array   ?$payload.failed Если bulk_action_failed_partially: список упавших.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {BulkActionResponse}
 * @response 202 {DelayedResponse} Если Action\Async — уход в background.
 * @response 422 {ValidationErrorResponse} На parameters либо `bulk_action_failed_partially`.
 * @response 403 {ForbiddenErrorResponse} Permission action'а или Resource::update.
 * @response 404 {NotFoundErrorResponse} Action с таким именем не зарегистрирован.
 */
public function bulkAction(Request $request): JsonResponse;
```

Events: `Admin\Events\BulkActionStarted` → `BulkActionCompleted` или `BulkActionFailed`.

**Пример:**

```http
POST /api/admin/users/bulkAction
{
  "action": "activate",
  "ids": [1, 2, 3]
}
```

```json
{
  "success": true,
  "payload": { "affected": 3, "message": "Активировано: 3", "refresh": true }
}
```

---

## `users.singleAction`

```php
/**
 * Выполнить row-action на одной записи.
 *
 * @input integer $id
 * @input string  $action Имя action.
 * @input object  ?$parameters Параметры action'а.
 *
 * @output object $payload
 * @output object ?$payload.record Обновлённая запись (если action её меняет).
 * @output string $payload.message
 * @output string ?$payload.redirect_url Если action делает navigate.
 * @output boolean $payload.refresh
 * @output string ?$payload.download_url Для Action\Export single-record.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SingleActionResponse}
 * @response 202 {DelayedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 * @response 404 {NotFoundErrorResponse} Запись или action не найдены.
 */
public function singleAction(Request $request): JsonResponse;
```

Events: `Admin\Events\ActionDispatched` → `ActionCompleted` или `ActionFailed`.

---

## `users.actionParameters`

```php
/**
 * Получить метаданные модалки action'а (когда Action::parameters() задан).
 * SPA вызывает перед открытием модалки, чтобы получить актуальную схему полей.
 *
 * @input string $action Имя action.
 * @input integer ?$id Для row-action (single context).
 *
 * @output object $payload
 * @output string $payload.title Заголовок модалки.
 * @output string ?$payload.description
 * @output array  $payload.fields Список FieldSchema.
 * @output string $payload.submit_label
 * @output string $payload.cancel_label
 * @output object ?$payload.confirm message + title.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ActionParametersResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function actionParameters(Request $request): JsonResponse;
```

---

## Bulk-restore / Bulk-force-delete

Для soft-deletes — это обычные `bulkAction` с зарезервированными именами `restore` и `forceDelete`:

```http
POST /api/admin/users/bulkAction
{
  "action": "restore",
  "ids": [10, 11, 12]
}
```

Permissions: `<resource>.restore` для `restore`, `<resource>.force-delete` для `forceDelete` (отдельный, обычно у Super Admin). Confirm-диалог обязателен на клиенте для destructive actions.

---

## Specialized actions (отдельные методы, не bulkAction/singleAction)

Эти действия слишком специфичны, чтобы быть `singleAction`/`bulkAction`. Они зарегистрированы как самостоятельные actions Resource-controller'а:

- `restore` (single) → см. [resources.md](resources.md).
- `forceDelete` (single) → см. [resources.md](resources.md).
- `replicate` → см. [resources.md](resources.md).
- `reorder` (bulk порядок) → см. [resources.md](resources.md).

Impersonation — у `auth`-controller'а: `auth.startImpersonation` / `auth.stopImpersonation`. См. [auth.md](auth.md).

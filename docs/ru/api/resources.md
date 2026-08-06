# API: Resources

Каждый зарегистрированный Resource превращается в отдельный controller со slug = `Resource::slug()` (snake_case от имени класса). Все CRUD- и расширенные actions вызываются как `api/admin/{resource_slug}/{action}`.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md). Actions (bulk/single) — [actions.md](actions.md). Export/Import — [exports-imports.md](exports-imports.md).

---

## Структура контроллера

`ResourceCompiler` создаёт runtime-контроллер для каждого Resource (либо использует один общий с DI Resource'а — выбирается в реализации). Базовый класс — `CrudController` из `dskripchenko/laravel-api`, расширенный admin-специфичными actions.

```php
final class CompiledResourceController extends CrudController
{
    public function __construct(public readonly Resource $resource) {}

    public function service(): CrudServiceInterface
    {
        return new ResourceCrudService($this->resource);
    }

    // меta, search, read, create, update, delete, deleteRestore — унаследованы из CrudController
    // Ниже — admin-специфичные actions.
}
```

Slug controller'а — `Resource::slug()` (например, `users`). Все примеры ниже — для Resource со slug `users`.

---

## Базовые CRUD-actions (CrudController)

### `users.meta`

```php
/**
 * Получить метаданные ресурса (поля формы, колонки таблицы, фильтры, доступные actions).
 * Унаследован из CrudController, расширен полным набором admin-схем.
 *
 * @output object $payload Meta-инфо.
 * @output array  $payload.fields Список FieldSchema (см. system.manifest).
 * @output array  $payload.columns Список ColumnSchema.
 * @output array  $payload.filters Список FilterSchema.
 * @output array  $payload.actions Список ActionSchema.
 * @output array  $payload.permissions Доступные permissions для текущего юзера.
 * @output object $payload.features softDeletes/replicable/reorderable/exportable/importable/polling.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceMetaResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Требуется <resource>.view.
 */
public function meta(Request $request): JsonResponse;
```

### `users.search`

```php
/**
 * Получить список записей с фильтрами, сортировкой и пагинацией.
 * Унаследован из CrudController.
 *
 * @input integer ?$page (default 1).
 * @input integer ?$per_page (default 25, max 100).
 * @input array ?$filters Список фильтров.
 * @input string $filters[].column Имя колонки.
 * @input string $filters[].operator Оператор: =, !=, >, >=, <, <=, like, not_like, in, not_in, between, is_null, is_not_null.
 * @input mixed  ?$filters[].value Значение (или массив для in/between).
 * @input array ?$order Сортировка.
 * @input string $order[].column
 * @input string $order[].direction asc|desc.
 * @input string ?$q Free-text по searchableFields().
 * @input string ?$trashed active|with|only Default active. Только если Resource::softDeletes()=true.
 * @input string ?$group_by Колонка из Resource::groupable() (опц).
 * @input array  ?$with Eager-load relations (whitelist в Resource::with()).
 * @input integer ?$view_id Применить saved view (override остальных параметров).
 *
 * @output object $payload
 * @output array  $payload.data Записи.
 * @output object $payload.meta Пагинация + summary + groups.
 * @output integer $payload.meta.page
 * @output integer $payload.meta.per_page
 * @output integer $payload.meta.total
 * @output integer $payload.meta.last_page
 * @output object  ?$payload.meta.summary Footer-агрегаты.
 * @output array   ?$payload.meta.groups Группы при group_by.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceSearchResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function search(Request $request): JsonResponse;
```

### `users.read`

```php
/**
 * Получить одну запись для edit-формы.
 *
 * @input integer $id ID записи.
 * @input array ?$with Eager-load relations.
 * @input boolean ?$include_relations Подгрузить has-many для RelationTable (default false).
 *
 * @header string ?$If-None-Match Etag.
 *
 * @output object $payload
 * @output object $payload.record Данные модели в плоском dot-notation формате.
 * @output object $payload.state Initial state формы (с reactive resolvers).
 * @output object $payload.permissions
 * @output boolean $payload.permissions.update
 * @output boolean $payload.permissions.delete
 * @output boolean $payload.permissions.force_delete
 * @output boolean $payload.permissions.restore
 * @output boolean $payload.permissions.replicate
 * @output object $payload.audit_summary
 * @output string $payload.etag Для optimistic concurrency.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceReadResponse}
 * @response 304 {NotModifiedResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function read(Request $request): JsonResponse;
```

### `users.create`

```php
/**
 * Создать запись.
 *
 * @input string $name Имя.
 * @input string(email) $email Email.
 * @input string $password Пароль.
 * @input integer $role_id Роль.
 * @input boolean ?$is_active.
 * @input array  ?$addresses Список адресов (RelationTable).
 * @input string $addresses[].city
 * @input string $addresses[].zip
 * @input object ?$meta KeyValue.
 *
 * @header string ?$X-Idempotency-Key Идемпотентность (рекомендуется).
 *
 * @output object $payload
 * @output object $payload.record Созданная запись.
 * @output string $payload.redirect_url
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {ResourceCreatedResponse}
 * @response 202 {DelayedResponse} Если хендлер ушёл в delayed-process.
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.create.
 */
public function create(Request $request): JsonResponse;
```

> Конкретный набор `@input` определяется `Resource::fields()` и сериализатор docblock'ов их подставляет автоматически. Пример выше — для UserResource. Для других Resource'ов docblock-генератор формирует свой состав полей.

Events: `Admin\Events\ResourceSaved` (event=`created`, audit).

### `users.update`

```php
/**
 * Обновить запись.
 *
 * @input integer $id ID записи.
 * @input string ?$name (и т.д., все поля из fields()).
 *
 * @header string ?$If-Match Etag для optimistic concurrency.
 *
 * @output object $payload
 * @output object $payload.record
 * @output object $payload.state
 * @output string $payload.etag Новый.
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceUpdatedResponse}
 * @response 202 {DelayedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 409 {ConflictResponse} If-Match не совпал, payload.current — свежая запись.
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function update(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceSaved` (event=`updated`, audit).

### `users.delete`

```php
/**
 * Удалить запись (soft, если SoftDeletes; иначе hard).
 *
 * @input integer $id
 *
 * @output object $payload
 * @output object ?$payload.record Запись после soft-delete (null если hard).
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceDeletedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.delete.
 */
public function delete(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceDeleted`.

---

## Расширенные actions

### `users.inlineEdit`

```php
/**
 * Inline-edit одной ячейки таблицы (для editable-колонок).
 * Validation rules применяются только к указанному полю.
 *
 * @input integer $id ID записи.
 * @input string  $field Имя поля.
 * @input mixed   $value Новое значение.
 *
 * @output object $payload
 * @output object $payload.record id и обновлённое поле.
 * @output string ?$payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {InlineEditResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function inlineEdit(Request $request): JsonResponse;
```

### `users.restore`

```php
/**
 * Восстановить soft-deleted запись.
 *
 * @input integer $id
 *
 * @output object $payload
 * @output object $payload.record
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceRestoredResponse}
 * @response 404 {NotFoundErrorResponse} Запись не существует или не была удалена.
 * @response 403 {ForbiddenErrorResponse} <resource>.restore.
 */
public function restore(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceRestored`.

### `users.forceDelete`

```php
/**
 * Жёсткое удаление soft-deleted записи (hard delete).
 *
 * @input integer $id
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.force-delete.
 */
public function forceDelete(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceForceDeleted`.

### `users.replicate`

```php
/**
 * Клонировать запись.
 *
 * @input integer $id Source ID.
 * @input object ?$overrides Поля для подмены при копировании.
 * @input array ?$with_relations Какие has-many реплицировать.
 *
 * @output object $payload
 * @output object $payload.record Клон.
 * @output string $payload.redirect_url
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {ResourceCreatedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.create + <resource>.replicate.
 */
public function replicate(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceReplicated`.

### `users.reorder`

```php
/**
 * Bulk-обновление порядка записей (для Resource::reorderable()).
 *
 * @input array $items Список.
 * @input integer $items[].id ID.
 * @input integer $items[].position Новый порядок.
 *
 * @output object $payload
 * @output integer $payload.affected
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AffectedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function reorder(Request $request): JsonResponse;
```

Events: `Admin\Events\ResourceReordered`.

### `users.view`

```php
/**
 * Получить infolist (read-only view) записи.
 *
 * @input integer $id
 *
 * @header string ?$If-None-Match
 *
 * @output object $payload
 * @output object $payload.record Сериализованные значения по схеме infolist.
 * @output array  $payload.layout Структура layout (Entries).
 * @output string $payload.etag
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {InfolistResponse}
 * @response 304 {NotModifiedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function view(Request $request): JsonResponse;
```

### `users.audit`

```php
/**
 * Получить историю изменений конкретной записи.
 *
 * @input integer $id
 * @input integer ?$page (default 1).
 * @input integer ?$per_page (default 50).
 *
 * @output object $payload
 * @output array  $payload.data Список AuditLogEntry (см. system.audit).
 * @output object $payload.meta Пагинация.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {AuditListResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function audit(Request $request): JsonResponse;
```

### `users.reactiveField`

```php
/**
 * Reactive field — догрузка зависимого поля при изменении другого.
 * Например, region_id зависит от country_id: при выборе страны фронт
 * шлёт запрос с context.country_id, получает обновлённый options/value/visible.
 *
 * @input integer ?$id ID записи (отсутствует на create-странице).
 * @input string  $field Имя поля.
 * @input object  ?$context Значения reactive-зависимостей.
 *
 * @output object $payload
 * @output string $payload.field
 * @output array  ?$payload.options Для select.
 * @output mixed  ?$payload.value Вычисленное значение.
 * @output boolean ?$payload.visible canSee().
 * @output array  ?$payload.rules Динамические правила.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ReactiveFieldResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function reactiveField(Request $request): JsonResponse;
```

---

## Relations

### `users.relationsList`

```php
/**
 * Получить список связанных записей с пагинацией. Используется внутри
 * Field\RelationTable или для отдельной табы с child-resource.
 *
 * @input integer $id ID родителя.
 * @input string  $relation Имя relation на модели.
 * @input integer ?$page
 * @input integer ?$per_page
 * @input array   ?$filters
 * @input array   ?$order
 *
 * @output object $payload
 * @output array  $payload.data Связанные записи.
 * @output object $payload.meta Пагинация.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ResourceSearchResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function relationsList(Request $request): JsonResponse;
```

### `users.relationsAttach`

```php
/**
 * Привязать запись к relation: BelongsToMany через attach либо HasMany через inline-create.
 *
 * @input integer $id ID родителя.
 * @input string  $relation
 * @input array   ?$ids Для attach (BelongsToMany).
 * @input integer ?$ids[] ID для привязки.
 * @input object  ?$pivot Дополнительные pivot-поля.
 * @input object  ?$create Для inline-create (HasMany).
 *
 * @output object $payload
 * @output mixed  $payload.related Привязанная запись либо массив.
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {RelationAttachedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function relationsAttach(Request $request): JsonResponse;
```

### `users.relationsDetach`

```php
/**
 * Detach или удалить связанную запись.
 *
 * @input integer $id ID родителя.
 * @input string  $relation
 * @input integer $related_id ID связанной записи.
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function relationsDetach(Request $request): JsonResponse;
```

### `users.relationsSync`

```php
/**
 * Полная замена BelongsToMany связи.
 *
 * @input integer $id
 * @input string  $relation
 * @input array   $ids Полный целевой список ID.
 *
 * @output object $payload
 * @output integer $payload.attached
 * @output integer $payload.detached
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {RelationSyncResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.update.
 */
public function relationsSync(Request $request): JsonResponse;
```

---

## Saved Views

### `users.viewsList`

```php
/**
 * Получить список сохранённых views (личных + shared).
 *
 * @output object $payload
 * @output array  $payload.data Список SavedView.
 * @output integer $payload.data[].id
 * @output string  $payload.data[].name
 * @output object  $payload.data[].payload Filter+sort+columns+group_by+per_page.
 * @output boolean $payload.data[].is_shared
 * @output boolean $payload.data[].is_default
 * @output object  ?$payload.data[].owner
 * @output string(date-time) $payload.data[].created_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SavedViewsListResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.view.
 */
public function viewsList(Request $request): JsonResponse;
```

### `users.viewSave`

```php
/**
 * Сохранить текущее состояние таблицы как именованный view.
 *
 * @input string $name Имя.
 * @input object $payload_data Текущие настройки.
 * @input object $payload_data.filter
 * @input string ?$payload_data.sort
 * @input array  ?$payload_data.columns
 * @input string ?$payload_data.group_by
 * @input integer ?$payload_data.per_page
 * @input boolean ?$is_shared
 * @input boolean ?$is_default
 *
 * @output object $payload
 * @output object $payload.view SavedView.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {SavedViewResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function viewSave(Request $request): JsonResponse;
```

### `users.viewUpdate`

```php
/**
 * Обновить view. Доступно только владельцу.
 *
 * @input integer $id
 * @input string ?$name
 * @input object ?$payload_data
 * @input boolean ?$is_shared
 * @input boolean ?$is_default
 *
 * @output object $payload
 * @output object $payload.view
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SavedViewResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Только владелец.
 */
public function viewUpdate(Request $request): JsonResponse;
```

### `users.viewDelete`

```php
/**
 * Удалить view. Доступно владельцу либо admin.systems.views.manage для shared.
 *
 * @input integer $id
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function viewDelete(Request $request): JsonResponse;
```

### `users.viewApply`

```php
/**
 * Пометить view как default для текущего юзера.
 *
 * @input integer $id
 *
 * @output object $payload
 * @output object $payload.view
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SavedViewResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function viewApply(Request $request): JsonResponse;
```

---

## Preferences

### `users.preferencesGet`

```php
/**
 * Получить пользовательские настройки таблицы (column visibility/order, default per_page).
 *
 * @output object $payload
 * @output object $payload.preferences
 * @output array  $payload.preferences.columns
 * @output string $payload.preferences.columns[].name
 * @output boolean $payload.preferences.columns[].visible
 * @output integer $payload.preferences.columns[].order
 * @output integer ?$payload.preferences.per_page
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {TablePreferencesResponse}
 */
public function preferencesGet(Request $request): JsonResponse;
```

### `users.preferencesSet`

```php
/**
 * Сохранить настройки таблицы.
 *
 * @input array  $columns
 * @input string $columns[].name
 * @input boolean $columns[].visible
 * @input integer $columns[].order
 * @input integer ?$per_page
 *
 * @output object $payload
 * @output object $payload.preferences
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {TablePreferencesResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function preferencesSet(Request $request): JsonResponse;
```

---

## Permissions

| Action | Default permission |
|---|---|
| `meta`, `search`, `read`, `view`, `audit`, `reactiveField`, `relationsList`, `viewsList`, `preferencesGet` | `<resource>.view` |
| `create` | `<resource>.create` |
| `update`, `inlineEdit`, `reorder`, `relationsAttach`, `relationsDetach`, `relationsSync`, `viewSave`, `viewUpdate`, `viewDelete`, `viewApply`, `preferencesSet` | `<resource>.update` |
| `delete` | `<resource>.delete` |
| `restore` | `<resource>.restore` |
| `forceDelete` | `<resource>.force-delete` |
| `replicate` | `<resource>.create` + `<resource>.replicate` |

Permissions проверяются через middleware `AdminAccess` (объявляется в `actionsMap()` ResourceCompiler'а), либо явно через `$this->authorize(...)` в action.

# API: Resources

Каждый зарегистрированный Resource превращается в отдельный ключ контроллера в `AdminApi::getMethods()`. Ключ — `Resource::slug()`, все actions вызываются как `/api/admin/{slug}/{action}`.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md). Действия ресурса (`action`) — [actions.md](actions.md). Экспорт и импорт — [exports-imports.md](exports-imports.md).

---

## Как устроен контроллер

`ResourceCompiler` (`src/Resource/ResourceCompiler.php`) проходит по `ResourceRegistry` текущей панели и для каждого ресурса добавляет запись:

```php
'users' => [
    'controller' => ResourceController::class,
    'actions' => [
        'meta'   => ['method' => ['get'],  'middleware' => [AdminAccess::class.':admin.users.view']],
        'search' => ['method' => ['post'], 'middleware' => [AdminAccess::class.':admin.users.view']],
        // ...
    ],
],
```

Класс контроллера у всех ресурсов один — `ResourceController`. Какой ресурс обслуживать, он узнаёт из ключа контроллера запроса (`ApiRequest::getApiControllerKey()`), то есть из slug'а.

- **Slug** — имя класса без суффикса `Resource`, во множественном числе, в kebab-case: `UserResource` → `users`, `BlogPostResource` → `blog-posts`.
- **База прав** — `Resource::permission()`, по умолчанию `admin.{slug}`. Права отдельных actions строятся от неё: `admin.users.view`, `admin.users.create` и т.д.

Права проверяет middleware `AdminAccess`. Если права нет, ответ `403` с `errorKey: forbidden`; если нет пользователя — `401` с `errorKey: unauthenticated`.

Все примеры ниже — для ресурса со slug `users`.

---

## Набор actions

| Action | HTTP | Право | Регистрируется |
|---|---|---|---|
| `meta` | GET | `<base>.view` | всегда |
| `search` | POST | `<base>.view` | всегда |
| `summary` | POST | `<base>.view` | всегда |
| `read` | GET | `<base>.view` | всегда |
| `create` | POST | `<base>.create` | всегда |
| `update` | POST | `<base>.update` | всегда |
| `inlineUpdate` | POST | `<base>.update` | всегда |
| `delete` | POST | `<base>.delete` | всегда |
| `export` | GET, POST | `<base>.view` | всегда, см. [exports-imports.md](exports-imports.md) |
| `action` | POST | `<base>.view` (+ право самого действия) | всегда, см. [actions.md](actions.md) |
| `listScreen` | GET | `<base>.view` | всегда |
| `createScreen` | GET | `<base>.create` | всегда |
| `editScreen` | GET | `<base>.update` | всегда |
| `viewScreen` | GET | `<base>.view` | всегда |
| `restore` | POST | `<base>.restore` | только если модель использует `SoftDeletes` |
| `forceDelete` | POST | `<base>.force-delete` | только если модель использует `SoftDeletes` |
| `replicate` | POST | `<base>.replicate` | только если `replicable()` возвращает `true` |
| `reorder` | POST | `<base>.reorder` | только если `reorderable()` возвращает `true` |
| `tree`, `treeScreen` | POST, GET | `<base>.view` | только если `hierarchyParentKey()` не `null` |
| `listener` | POST | `<base>.view` (+ `<base>.create`/`<base>.update` внутри) | только если `formLayout('create')` или `formLayout('update')` содержит `Layout\Listener` |

Action, которого нет в таблице для конкретного ресурса, просто не существует: маршрут не зарегистрирован.

Если `Resource::savedViews()` возвращает `true`, рядом регистрируется отдельный контроллер `{slug}_views` — см. [Saved views](#saved-views).

---

## Метаданные и страницы

### `users.meta`

```php
/**
 * Метаданные ресурса: поля, колонки, фильтры, действия.
 *
 * @output object $payload Resource::meta().
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {ResourceMetaResponse}
 */
public function meta(): JsonResponse;
```

Ключи `payload` (из `Resource::meta()`):

| Ключ | Что внутри |
|---|---|
| `slug`, `label`, `icon`, `group` | идентификация ресурса и место в меню |
| `subject_type` | morph-класс модели (алиас из `morphMap` или FQCN) — нужен для `audit.timeline` |
| `permissions` | имена прав: `view`, `create`, `update`, `delete`, `restore`, `force_delete`, `replicate`, `reorder` (строки вида `admin.users.view`, а не флаги доступа) |
| `fields` | поля формы |
| `create_fields` | поля формы создания, если она отличается от формы редактирования |
| `columns` | колонки таблицы |
| `infolist` | записи read-only просмотра |
| `filters` | фильтры; для модели с `SoftDeletes` автоматически добавляется `TrashedFilter` |
| `actions` | действия ресурса, **которые текущий пользователь может выполнить**: действие с `permission()`, которого у пользователя нет, или с ложным `canSee()` сюда не попадает (у `DropDown` так же отфильтровываются вложенные `items`) |
| `searchable` | колонки, по которым работает `q` |
| `with`, `view_mode` (`list`/`tree`), `hierarchy_parent_key`, `parent_slug` | структура списка |
| `features` | `softDeletes`, `replicable`, `reorderable`, `reorderColumn`, `importable`, `exportable` (список форматов), `savedViews`, `polling`, `warnOnUnsavedChanges`, `creatable`, `editable` |

Та же структура (плюс блок `screens`) попадает в `resources[]` манифеста — см. [system.md](system.md).

### `users.listScreen`, `users.treeScreen`, `users.createScreen`, `users.editScreen`, `users.viewScreen`

Описания сгенерированных страниц (`GeneratedListScreen`, `GeneratedTreeScreen`, `GeneratedCreateScreen`, `GeneratedEditScreen`, `GeneratedViewScreen`). Формат ответа тот же, что у `state` обычного экрана (`state`, `name`, `description`, `layout`, `command_bar`, `permissions`, `etag`, см. [screens.md](screens.md)), плюс `type` (`generated.list`, `generated.edit`, …) и `resource_slug`.

- `editScreen` и `viewScreen` требуют `id`. Без него — `422` (`errorKey: validation`), если записи нет — `404` (`errorKey: not_found`).
- В `command_bar` (и в `layout`) попадают только действия, видимые текущему пользователю; правило то же, что для `meta.actions`.

Ответы: `{ResourceListScreenResponse}`, `{ResourceTreeScreenResponse}`, `{ResourceCreateScreenResponse}`, `{ResourceEditScreenResponse}`, `{ResourceViewScreenResponse}`.

---

## Список

### `users.search`

```php
/**
 * Список записей с фильтрами, сортировкой и пагинацией.
 *
 * @input integer ?$page
 * @input integer ?$per_page
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {ResourceSearchResponse}
 */
public function search(Request $request): JsonResponse;
```

Параметры:

| Параметр | Описание |
|---|---|
| `page` | номер страницы, по умолчанию 1 |
| `per_page` | по умолчанию `admin.pagination.default_per_page` (25), не больше `admin.pagination.max_per_page` (100) |
| `filters` | значения фильтров ресурса — словарь `{field: value}` или список `[{column, value}]`. Применяются только фильтры, объявленные в `filters()` (и автоматический `trashed`); как трактовать значение, решает сам фильтр |
| `q` | строка поиска: `LIKE %q%` по колонкам из `searchableFields()` |
| `ids` | вернуть только записи с этими ключами (так `ResourcePicker` подгружает выбранные записи) |
| `order` | `[{column, direction}]`, `direction` — `asc`/`desc`. Без `order`: для `reorderable()` ресурса — по `reorderColumn()` по возрастанию, иначе `defaultOrder()` (по умолчанию первичный ключ по убыванию) |
| `group_by` | колонка; в ответ добавляется `meta.groups` — `[{value, count}]` по всем записям под текущими фильтрами (без пагинации) |
| `picker` | `true` — к каждой строке добавляется `_picker` из `Resource::pickerItem()` |

Ответ:

```json
{
  "success": true,
  "payload": {
    "data": [ { "id": 1, "name": "Ivan" } ],
    "meta": {
      "page": 1, "per_page": 25, "total": 1, "last_page": 1,
      "from": 1, "to": 1,
      "summary": null,
      "groups": null
    }
  }
}
```

Строки — `Model::toArray()`. Если у ресурса есть редактируемые колонки и `editableForRow()` запрещает правку какой-то ячейки, в строке появляется `_editable: {column: false}`. `meta.summary` всегда `null` — агрегаты отдаёт отдельный `summary`.

### `users.summary`

Агрегаты по текущим фильтрам (`filters`) для колонок, у которых объявлен `summary([...])`. Поддерживаются `sum`, `avg`, `count`, `min`, `max`, `range` (`{min, max}`).

```json
{ "success": true, "payload": { "summary": { "amount": { "sum": 1520.5, "avg": 76.03 } } } }
```

Ответ: `{ResourceSummaryResponse}`.

### `users.tree`

Только для иерархических ресурсов. Принимает те же `filters` и `q`, что `search`, но без пагинации: все узлы загружаются одним запросом и собираются в дерево по `hierarchyParentKey()`.

```json
{
  "success": true,
  "payload": {
    "data": [
      { "key": 1, "label": "Root", "record": { "id": 1 }, "actions": [], "children": [ ] }
    ],
    "meta": { "total": 12, "max_depth": 3, "parent_key": "parent_id", "label_column": "name" }
  }
}
```

- `label` берётся из первой колонки с `searchable`, иначе из `name`.
- Узел, чей родитель отфильтрован, поднимается в корень.
- `actions` есть только у узлов, для которых `treeNodeActions()` что-то вернул; `children` отсутствует у листьев.
- Если ресурс не иерархический — `409` (`errorKey: not_hierarchical`).

Ответ: `{ResourceTreeResponse}`.

---

## Одна запись

### `users.read`

```php
/**
 * Одна запись по id.
 *
 * @input integer $id
 *
 * @output object $payload
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {ResourceReadResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function read(Request $request): JsonResponse;
```

Ответ — `{record, state}`, оба — `Resource::transformRecord()` (по умолчанию `toArray()`). Без `id` — `422` (`errorKey: validation`), нет записи — `404` (`errorKey: not_found`).

### `users.create`

```php
/**
 * Создаёт запись. Поля — из Resource::fields() и validationRules('create').
 *
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 201 {ResourceCreatedResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function create(Request $request): JsonResponse;
```

Тело — значения полей ресурса на верхнем уровне. Данные проходят `validationRules('create')`, HTML в полях `Wysiwyg` с включённой санитизацией очищается, переводимые поля сохраняются отдельно, остальное — через `fillModel()`.

Ответ `201`: `{record, redirect_url, message: "Created"}`, `redirect_url` — `/admin/r/{slug}/{id}`.

Ошибки уровня БД возвращаются как `422` с полями в `messages`:

| `errorKey` | Когда |
|---|---|
| `unique_violation` | нарушение уникальности (SQLSTATE 23505) |
| `not_null_violation` | не заполнено обязательное поле (23502) |
| `foreign_key_violation` | нарушена внешняя ссылка (23503) |
| `db_error` | любая другая ошибка запроса; текст SQL показывается только при `app.debug` |

### `users.update`

```php
/**
 * Обновляет запись. Кроме id — поля ресурса, проверяемые validationRules('update').
 *
 * @input integer $id
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {ResourceUpdatedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function update(Request $request): JsonResponse;
```

Правила `unique` без явного исключения автоматически исключают редактируемую запись. Ответ: `{record, state, message: "Updated"}`. Ошибки — как у `create`, плюс `404` (`not_found`) и `422` без `id`.

### `users.inlineUpdate`

```php
/**
 * Правка одной ячейки таблицы.
 *
 * @input integer $id
 * @input string $column
 * @input string $value
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {ResourceInlineUpdatedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function inlineUpdate(Request $request): JsonResponse;
```

Колонка должна быть объявлена в `columns()` как редактируемая; `value` проверяется её правилами `editable.validation`. Нередактируемая колонка — `422` (`errorKey: validation`). Ответ: `{record, column, value}`.

### `users.delete`

```php
/**
 * Удаляет запись: мягко, если модель использует SoftDeletes, иначе окончательно.
 *
 * @input integer $id
 *
 * @output object $payload
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {ResourceDeletedResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function delete(Request $request): JsonResponse;
```

Ответ: `{record, message: "Deleted"}`.

### `users.restore`

Только для моделей с `SoftDeletes`. Тело `{id}`; ищет запись с учётом удалённых и обнуляет колонку удаления. Ответ `200 {ResourceRestoredResponse}`: `{record, message: "Restored"}`. Нет записи — `404`.

### `users.forceDelete`

Только для моделей с `SoftDeletes`. Тело `{id}`; окончательно удаляет запись (в том числе уже мягко удалённую). Ответ `200 {ResourceForceDeletedResponse}`: `{id, message: "Force deleted"}`. Нет записи — `404`.

### `users.replicate`

Только при `replicable() === true`. Тело `{id}`; копия строится `Resource::replicate()` и сохраняется. Ответ `200 {ResourceReplicatedResponse}`: `{record, redirect_url, message: "Replicated"}`, где `redirect_url` — `/admin/r/{slug}/{id}/edit`. Нет исходной записи — `404`.

### `users.reorder`

Только при `reorderable() === true`. Перестановка строк перетаскиванием, одной транзакцией. Позиции пишутся в `reorderColumn()` (по умолчанию `position`). Тело — одна из двух форм.

Строки в новом порядке — так шлёт панель после перетаскивания на видимой странице:

```http
POST /api/admin/users/reorder
{ "ids": [3, 1, 2], "offset": 0 }
```

Строки обмениваются позициями, которые занимают сейчас: отсортированные по возрастанию, первая строка получает наименьшую. Поэтому страница постраничного или отфильтрованного списка переставляется внутри своих позиций и не сталкивается со строками других страниц. Если позиций нет или они повторяются, строки нумеруются от `offset` (по умолчанию `0`; панель передаёт индекс первой строки страницы).

Явные позиции, записываются как есть (`position` — целое ≥ 0):

```http
POST /api/admin/users/reorder
{ "items": [ { "id": 3, "position": 0 }, { "id": 1, "position": 1 } ] }
```

Ответ `200 {ResourceReorderedResponse}`: `{count, positions, message: "Reordered"}`, где `positions` — новые позиции по первичному ключу. Панель разрешает перетаскивание только в ручном порядке: без сортировки или по `reorderColumn()` по возрастанию.

### `users.listener`

Перерисовывает один из `Layout\Listener` формы по текущему состоянию.

| Параметр | Описание |
|---|---|
| `listener` | id слушателя, как его сериализовал layout (обязателен) |
| `state` | текущее состояние формы (объект) |
| `context` | `create` или `update`; если не передан — `update` при наличии `id`, иначе `create` |
| `id` | редактируемая запись |

Маршрут требует `<base>.view`, а право самой формы — `<base>.create` или `<base>.update` по `context` — проверяется внутри: без него `403` (`errorKey: forbidden`). Ответ `200 {ListenerResponse}`: `{listener, state, layouts}`. Ошибки: `422` (нет `listener`, `state` не объект, неверный `context`), `404` (`listener_not_found`, `listener_handler_not_callable`).

---

## Saved views

Контроллер `{slug}_views` (`Table\SavedViewsController`) регистрируется, только если `Resource::savedViews()` возвращает `true`. Все четыре action требуют `<base>.view`.

URL: `/api/admin/users_views/{action}`.

Сохранённый вид — именованное состояние списка (`state`: фильтры, сортировка, колонки — содержимое определяет клиент). Вид бывает личным (есть владелец) или глобальным (без владельца). Через API создаются только личные виды, а менять и удалять можно только свои.

### `users_views.list` (GET)

Личные виды текущего пользователя и все глобальные, сначала `is_default`, затем по имени.

```json
{
  "success": true,
  "payload": {
    "data": [ { "id": 5, "name": "Активные", "state": { }, "is_default": true, "owned": true } ]
  }
}
```

`owned` — `true` у личного вида, `false` у глобального. Ответ: `{SavedViewListResponse}`.

### `users_views.create` (POST)

| Параметр | Правила |
|---|---|
| `name` | обязателен, строка до 255 символов |
| `state` | обязателен, объект |
| `is_default` | необязателен, boolean |

Ответ `200 {SavedViewResponse}`: `{view: {id, name, state, is_default, owned}}`.

### `users_views.update` (POST)

`id` (обязателен) и любые из `name`, `state`, `is_default`. Чужой или глобальный вид — `403` (`errorKey: forbidden`), нет вида — `404`. Ответ: `{view}`.

### `users_views.delete` (POST)

`id`. Те же `403`/`404`. Ответ: `{id}`.

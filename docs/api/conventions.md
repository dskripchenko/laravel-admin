# API: общие конвенции

Все admin-эндпоинты реализованы поверх `dskripchenko/laravel-api`. Документ фиксирует обязательные правила, которым следует каждый файл в этой директории и каждый PHP-контроллер в `src/Http/Controllers/`.

> Конкретные эндпоинты (controllers + actions) — в соседних файлах: [system.md](system.md), [auth.md](auth.md), [profile.md](profile.md), [resources.md](resources.md), [actions.md](actions.md), [screens.md](screens.md), [settings.md](settings.md), [dashboards.md](dashboards.md), [exports-imports.md](exports-imports.md), [uploads.md](uploads.md), [delayed.md](delayed.md), [search.md](search.md), [health.md](health.md). Регистрация контроллеров в `AdminApi::getMethods()` — [registration.md](registration.md).

---

## 1. URL-паттерн (жёсткий)

```
{scheme}://{host}/{admin.api_path}/{controller}/{action}

Пример: https://example.com/api/admin/users/search
```

- `admin.api_path` (default `api/admin`) — из `config/admin.php`. Полный путь от корня хоста, **не вложен** в `admin.path` (где живёт SPA-shell).
- `{controller}` и `{action}` — **только эти два сегмента**. Никаких `{id}`, `{slug}`, `{relation}` в URL.
- Все параметры (id, фильтры, сортировка, реляции, нагрузки) идут через **Request body** (JSON для POST/PATCH/PUT/DELETE) или **query-string** (для GET).
- HTTP-метод (`GET`/`POST`/`PATCH`/`PUT`/`DELETE`) задаётся в `getMethods()` через `'method' => ['post']`. Для action'ов с одним методом — массив из одного значения.

**Запрещено** придумывать REST-URL вида `/resources/{slug}/{id}`. Если нужен `id` — он лежит в body/query как `id`. Если нужен slug — это уже выделенный controller с этим именем (например, `users`).

> SPA-shell живёт отдельно: `https://example.com/admin/*` (под `config('admin.path')`). API не вложен в shell-prefix.

## 2. Версионирование

`laravel-api` использует паттерн `api/{version}/{controller}/{action}`. У admin `version = 'admin'` — это внутренний идентификатор, не публично-стабильная версия. Маршрутизация управляется через `BaseModule::getApiVersionList()`:

```php
final class AdminApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'admin' => AdminApi::class,
            // 'admin-v2' => AdminApiV2::class,   // если понадобятся параллельные ветки
        ];
    }
}
```

Семвер: согласно ARCHITECTURE.md п.13.12, мажорные релизы admin-пакета могут ломать `/api/admin/...`. SPA пересобирается одновременно с core, и обещаний обратной совместимости нет. Внешние стабильные API строятся пользователем поверх admin отдельным `BaseModule` (например, `api/public/v1/...`).

## 3. Транспорт и заголовки

### Request

| Заголовок | Значение | Когда |
|---|---|---|
| `Accept` | `application/json` | всегда |
| `Content-Type` | `application/json` | для POST/PATCH/PUT/DELETE с JSON-телом |
| `Content-Type` | `multipart/form-data` | только для upload-action'ов |
| `Authorization` | `Bearer {token}` | при использовании Sanctum-токена |
| `X-XSRF-TOKEN` | значение cookie `XSRF-TOKEN` | при cookie-сессии (axios шлёт авто) |
| `X-Admin-Locale` | например `ru` | переопределить локаль на запрос |
| `If-None-Match` | etag | для условных GET'ов (manifest, view) |
| `X-Idempotency-Key` | UUID | для идемпотентных POST'ов (create, bulk-actions) |

### Response

| Заголовок | Значение |
|---|---|
| `Content-Type` | `application/json; charset=utf-8` |
| `ETag` | для cacheable-ответов |
| `X-RateLimit-Limit` / `X-RateLimit-Remaining` | для throttled-actions |
| `Retry-After` | при `429 Too Many Requests` |

## 4. Формат ответа: единый конверт laravel-api

```json
// Успех
{ "success": true, "payload": <PAYLOAD> }

// Ошибка
{ "success": false, "payload": { "errorKey": "validation", "message": "...", "messages": {...} } }

// Delayed (фоновая операция)
{ "success": true, "payload": { "delayed": { "uuid": "...", "status": "new", "progress": 0, "message": null } } }
```

В контроллерах используем helper'ы из `Dskripchenko\LaravelApi\Http\Controllers\ApiController`:

```php
return $this->success($data);                 // {success: true, payload: data}
return $this->created($data);                 // 201
return $this->error('not_found', '...', 404);
return $this->validationFailed($validator);   // 422
return $this->delayed($processHandle);        // {success: true, payload: {delayed: {...}}}
```

## 5. HTTP-коды и errorKey

| HTTP | errorKey | Условие |
|---|---|---|
| 200 | — | успех (GET, PATCH, DELETE с пустым payload отдают 200 + null) |
| 201 | — | create |
| 202 | — | принято в фон (вместе с `payload.delayed`) |
| 304 | — | `If-None-Match` совпал |
| 400 | `bad_request` | синтаксис ОК, бизнес-параметры невалидны |
| 401 | `unauthenticated` | нет/истёк session/token |
| 401 | `two_factor_required` | логин ОК, нужен TOTP |
| 403 | `forbidden` | нет permission |
| 404 | `not_found` | resource/screen/record не существует |
| 409 | `conflict` | optimistic-lock конфликт |
| 410 | `gone` | force-deleted |
| 422 | `validation` | валидация формы |
| 423 | `locked` | soft-lock на редактирование |
| 429 | `throttled` | rate-limit |
| 500 | `server_error` | unhandled exception |

## 6. Обязательные требования к контроллерам

Каждый action-метод **обязан** иметь полный docblock. OpenAPI-спецификация генерируется из него автоматически. Без docblock метод не должен попадать в production.

### Шаблон docblock

```php
/**
 * Краткое описание действия (1 строка).
 *
 * Расширенное описание (опционально, неограниченно).
 * Можно описать бизнес-контекст, побочные эффекты, замечания по производительности.
 *
 * @input  TYPE [?]$NAME Описание.
 * @input  TYPE $foo.bar Вложенное поле (dot-notation).
 * @input  TYPE $items[].id Поле в элементе массива.
 *
 * @output TYPE [?]$NAME Описание.
 * @output object $payload Корневая обёртка ответа.
 * @output integer $payload.id Идентификатор.
 *
 * @header string $X-Idempotency-Key Идемпотентность.
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {Action200Response}
 * @response 401 {UnauthenticatedError}
 * @response 422 {ValidationError}
 *
 * @deprecated Use users.update instead (only on legacy actions).
 */
public function action(Request $request): JsonResponse
{
    // ...
}
```

### Поддерживаемые типы в `@input` / `@output`

- Базовые: `string`, `integer`, `number`, `boolean`, `object`, `array`, `file`.
- Форматы: `string(email)`, `string(date-time)`, `string(uuid)`, `integer(int64)`, `number(float)`.
- Опциональность: `string ?$name` (опциональное), `string $name` (обязательное), `integer!` (явный required).
- Enum: `string $status [draft,pending,confirmed]`.
- Вложенность: `object $address`, потом `string $address.city`.
- Массивы объектов: `array $items`, потом `integer $items[].id`.
- Ссылки на схемы: `@output {OrderSchema}` или массив `@output {OrderSchema[]}`.

### Security schemes

В admin определены три схемы:

| Схема | Где задаётся | Описание |
|---|---|---|
| `AdminSession` | cookie + CSRF | сессионная аутентификация SPA |
| `AdminBearer` | `Authorization: Bearer ...` | Sanctum API-токен (если Sanctum установлен) |
| `Public` | — | публичные endpoint'ы (login, forgot-password) |

Action может перечислить несколько `@security` — это значит «любая из них подходит».

### `@response` шаблоны

Многократно используемые схемы ответов выносятся в named-templates:

- `{Success}` — успех с пустым payload (`{ success: true, payload: null }`).
- `{ValidationError}` — `422` с `errorKey: validation` + `messages`.
- `{UnauthenticatedError}` — `401`.
- `{ForbiddenError}` — `403`.
- `{NotFoundError}` — `404`.
- `{DelayedResponse}` — `202` с `payload.delayed`.

Шаблоны определяются в `src/Http/Schemas/` (см. registration.md §3).

## 7. Регистрация actions в `getMethods()`

Каждый action описывается в `AdminApi::getMethods()`:

```php
'controllers' => [
    'system' => [
        'controller' => SystemController::class,
        'middleware' => [/* controller-level */],
        'actions' => [
            'manifest' => [
                'method' => ['get'],
                'middleware' => [],                       // дополнительные на этот action
            ],
            'me' => [
                'method' => ['get'],
            ],
            'notificationsMarkAllRead' => [
                'method' => ['post'],
            ],
        ],
    ],
],
```

Полный пример с генерацией Resource-контроллеров — [registration.md](registration.md).

## 8. Пагинация

Пагинация — через CrudController/CrudService. В action `search` параметры:

```json
{
  "page": 1,
  "per_page": 25,
  "filters": [
    { "column": "email", "operator": "like", "value": "ivan" },
    { "column": "is_active", "operator": "=", "value": true }
  ],
  "order": [
    { "column": "created_at", "direction": "desc" }
  ]
}
```

Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not_like`, `in`, `not_in`, `between`, `not_between`, `is_null`, `is_not_null`.

Response:

```json
{
  "success": true,
  "payload": {
    "data": [/* записи */],
    "meta": {
      "page": 1, "per_page": 25, "total": 1234, "last_page": 50,
      "from": 1, "to": 25,
      "summary": null,
      "groups": null
    }
  }
}
```

Cursor-пагинация (опциональна) — через `cursor` параметр в Request, см. [resources.md](resources.md).

## 9. Idempotency

Action'ы, изменяющие state, поддерживают `X-Idempotency-Key` header. Сервер кэширует результат на N минут (default 30) и отдаёт тот же ответ при повторе.

Примеры action'ов с обязательной идемпотентностью: `users.create`, `users.update`, `users.bulkAction`, `users.runImport`, `users.runExport`.

## 10. Throttling

Rate-limit задаётся в `getMethods()` через middleware:

```php
'middleware' => [
    \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
],
```

Per-action overrides:

```php
'login' => [
    'method' => ['post'],
    'middleware' => [\Illuminate\Routing\Middleware\ThrottleRequests::class . ':5,1'],
],
```

При превышении: `429` + `Retry-After`.

## 11. Permissions

В каждом action **обязательно** проверка permission'а явно через middleware либо в самом методе:

```php
public function action(Request $request): JsonResponse
{
    $this->authorize('admin.users.update');  // через AdminPolicy
    // ...
}
```

Либо middleware-уровнем в `getMethods()`:

```php
'create' => [
    'method' => ['post'],
    'middleware' => [\Dskripchenko\LaravelAdmin\Http\Middleware\AdminAccess::class . ':admin.users.create'],
],
```

## 12. Локаль

Резолвится middleware `AdminLocale`. Переопределение на запрос — через `?locale=...` или header `X-Admin-Locale: ...`. Все строки в `payload.message` и `payload.messages` локализуются.

## 13. Telemetry

Каждый action эмитит Laravel-events `Admin\Events\*` (см. ARCHITECTURE.md п.13.16):

- `Admin\Events\ApiActionStarted` (controller, action, user_id, params)
- `Admin\Events\ApiActionCompleted` / `ApiActionFailed`
- Action-specific: `ResourceQueried`, `ResourceSaved`, `ResourceDeleted`, `ActionDispatched`, `ValidationFailed`, ...

Host-проект слушает их через стандартный `Event::listen()`.

## 14. Документация (Scalar UI)

`laravel-api` генерирует:

- `GET /api/admin/openapi.json` — спецификация OpenAPI 3.0 (из docblock'ов всех action'ов).
- `GET /api/admin/doc` — **Scalar UI** для интерактивного просмотра. Доступ ограничен permission'ом `admin.system.api-docs`.
- TypeScript-интерфейсы могут быть сгенерированы через artisan `api:client admin` (команда из laravel-api).
- Postman-collection и `.http`-files — тоже через laravel-api.

## 15. Тестирование

Тесты вызывают action'ы по их полному пути:

```php
$response = $this->postJson('/api/admin/users/create', [
    'name'  => 'New User',
    'email' => 'new@example.com',
]);

$response->assertSuccessful();
expect($response->json('payload.id'))->toBeInt();
```

Helper'ы из `ResourceTestCase`:

```php
$this->actingAsAdmin($admin, ['admin.users.create'])
    ->callAction('users.create', ['name' => 'X', 'email' => 'x@y.z'])
    ->assertSuccessful()
    ->assertResourceCreated(['email' => 'x@y.z']);
```

`callAction('users.create', $payload)` под капотом — `postJson('/api/admin/users/create', $payload)`.

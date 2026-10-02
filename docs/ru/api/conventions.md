# API: общие конвенции

Правила, общие для всех эндпоинтов панели: адресация, заголовки, формат ответа, ошибки, права, пагинация, ограничение частоты, локаль.

> Конкретные контроллеры описаны в соседних файлах: [system.md](system.md), [auth.md](auth.md), [profile.md](profile.md), [resources.md](resources.md), [actions.md](actions.md), [screens.md](screens.md), [settings.md](settings.md), [dashboards.md](dashboards.md), [exports-imports.md](exports-imports.md), [uploads.md](uploads.md), [delayed.md](delayed.md), [search.md](search.md), [health.md](health.md). Как собирается карта эндпоинтов — [registration.md](registration.md).

---

## 1. URL-паттерн

```
{scheme}://{host}/{laravel-api.prefix}/{version}/{controller}/{action}

Пример: https://example.com/api/admin/users/search
```

- `laravel-api.prefix` — по умолчанию `api`. Если хост задаёт свой префикс (например `api/v1`), API панели переезжает под него (`/api/v1/admin/...`), и SPA следует за ним: путь для SPA выводится из того же префикса (`Panel::defaultApiPath()`).
- `{version}` — идентификатор панели: `admin` для основной, ключ `admin.panels.{id}` для дополнительной. В URL панели нет отдельного номера версии; мажорные релизы ядра могут менять контракт, SPA собирается вместе с ядром.
- `admin.api_path` нужен только когда прокси переписывает путь, который видит браузер. Маршруты он не переносит.
- `{controller}` и `{action}` — единственные сегменты после версии. Идентификатор записи, фильтры, сортировка передаются в query-string (GET) или в теле (POST): `GET /api/admin/users/read?id=5`, `POST /api/admin/users/update` с `{"id": 5, ...}`.
- HTTP-метод задаётся в `getMethods()` для каждого action'а. Чтение — `GET` (у `export` — `GET` и `POST`), всё изменяющее состояние — `POST`. `PATCH`/`PUT`/`DELETE` панель не использует.

SPA-shell живёт отдельно — под `admin.path` (`/admin/*` по умолчанию) и в префикс API не вложен.

## 2. Версии и панели

`AdminApiModule::getApiVersionList()` возвращает `admin => AdminApi::class` и по одной версии на каждую дополнительную панель (`admin.panels.{id}.api`, наследник `Panel\PanelApi`). Хост-модуль может унаследовать `AdminApiModule` и добавить свои версии рядом с панелями (`...parent::getApiVersionList()`); подробнее — [registration.md §6](registration.md#6-каскад-middleware).

## 3. Заголовки

### Запрос

| Заголовок | Когда |
|---|---|
| `Accept: application/json` | всегда |
| `Content-Type: application/json` | POST с JSON-телом |
| `Content-Type: multipart/form-data` | загрузка файлов (`uploads/upload`, `uploads/image`, `import/upload`) |
| `X-XSRF-TOKEN` / `X-CSRF-TOKEN` | запросы с сессионной cookie: стек API включает группу `web`, а значит и CSRF-проверку Laravel |
| `Authorization: Bearer {token}` | программный доступ с персональным токеном из профиля (laravel/sanctum), схема `AdminBearer` |
| `X-Admin-Locale` | переопределить локаль на один запрос (см. §10) |
| `Accept-Language` | один из источников локали (см. §10) |
| `If-None-Match` | только `system/manifest`: при совпадении с ETag ответ `304` без тела |

### Ответ

| Заголовок | Когда |
|---|---|
| `Content-Type: application/json` | все JSON-ответы |
| `ETag` | `system/manifest` (значение — версия манифеста в кавычках) |
| `Vary: Accept-Language, X-Admin-Locale, Cookie` | добавляет `AdminLocale`: ответ зависит от языка, промежуточные кэши должны это учитывать |
| `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` | выставляет `ThrottleRequests` Laravel (см. §8) |

## 4. Конверт ответа

Все ответы action'ов — в конверте laravel-api (`ApiResponseHelper::say()`):

```json
// успех
{ "success": true, "payload": <PAYLOAD> }

// ошибка
{ "success": false, "payload": { "errorKey": "validation", "message": "...", "messages": { "email": ["..."] } } }
```

Помощники `Dskripchenko\LaravelApi\Controllers\ApiController`:

```php
return $this->success($data);              // 200, {success: true, payload: $data}
return $this->success($data, 202);         // любой статус вторым аргументом
return $this->created($data);              // 201
return $this->error(['errorKey' => 'not_found', 'message' => '...'], 404);
return $this->notFound('...');             // 404, errorKey = not_found
return $this->noContent();                 // 204 без тела
```

У `error()` статус по умолчанию — **200**, поэтому код ответа всегда передают явно. Исключение — `auth/login` при включённой 2FA: `success: false`, `errorKey: two_factor_required` и `challenge_token` приходят со статусом 200 намеренно.

Исключения, вылетевшие из action'а, перехватывает `BaseApi` и отдаёт `ApiErrorHandler`:

| Исключение | Ответ |
|---|---|
| `Illuminate\Validation\ValidationException` (в том числе из `$request->validate()`) | 422, `errorKey: validation`, `message`, `messages` (поле → массив строк) — обработчик регистрирует ядро в `AdminServiceProvider` |
| `HttpExceptionInterface` (`abort(...)`) | статус исключения, `errorKey: http_error` |
| любое другое | 500, только `message` (`Internal server error`, текст исключения — лишь в debug-режиме); исключение пишется в лог |

## 5. HTTP-коды и `errorKey`

Ключи ниже — все значения `errorKey`, которые возвращает код ядра.

### Общие (middleware и большинство контроллеров)

| HTTP | `errorKey` | Когда |
|---|---|---|
| 401 | `unauthenticated` | нет аутентификации на guard'е панели (`AdminAuth`, `AdminAccess`) |
| 401 | `session_expired` | пароль сменили после входа: сессия разлогинена (`AdminAuth`) |
| 403 | `account_inactive` | учётная запись отключена (`AdminAuth`, `auth/login`) |
| 403 | `forbidden` | нет нужного permission'а (`AdminAccess`), нет доступа к панели вообще (shared-стратегия), нет права на контекст формы в `listener`, обработчик не в allowlist (`delayed/run`) |
| 403 | `action_forbidden` | пользователь запускает действие, которое ему не показано (см. §6) |
| 404 | `not_found` | запись, уведомление, процесс, токен и т.п. не найдены |
| 422 | `validation` | ошибка валидации; `messages` — ошибки по полям |
| любой | `http_error` | `abort()` / `HttpException` внутри action'а |

### Ресурсы, экраны, действия

| HTTP | `errorKey` | Где |
|---|---|---|
| 422 | `unique_violation`, `not_null_violation`, `foreign_key_violation`, `db_error` | `create` / `update` ресурса: ошибка БД, переведённая в ошибку формы (`messages` по колонкам, где их удаётся определить) |
| 409 | `not_hierarchical` | `tree` у ресурса без `hierarchyParentKey()` |
| 422 | `unsupported_format` | `export` с неизвестным форматом |
| 404 | `unknown_action` | `{slug}/action` с `key`, которого нет в `actions()` ресурса |
| 501 | `action_not_implemented` | у действия нет метода на ресурсе |
| 422 / 500 | `action_failed` | метод действия бросил `ActionFailedException` (422) или другое исключение (500) |
| 400 | `screen_method_missing` | `runMethod` без `method` |
| 404 | `screen_method_not_callable` | метод экрана нельзя вызвать из API |
| 422 | `screen_method_arguments_missing` | у метода экрана не хватает аргументов |
| 404 | `screen_not_registered` | экран не зарегистрирован |
| 404 | `listener_not_found`, `listener_handler_not_callable` | `listener`: слушатель с таким id не найден / его обработчик не вызывается |
| 404 | `unknown_dashboard` | `dashboard/*` с неизвестным дашбордом |
| 500 | `delayed_run_failed` | `delayed/run` не смог запустить процесс |
| 422 | `forbidden_disk` | `uploads/serve` с диском не из `admin.uploads.servable_disks` |
| 422 | `unknown_resource`, `file_missing` | `import/*`: неизвестный ресурс / нет файла |

### Аутентификация, профиль, система

| HTTP | `errorKey` | Где |
|---|---|---|
| 401 | `invalid_credentials` | `auth/login` |
| 200 | `two_factor_required` | `auth/login`: нужен второй фактор, в payload `challenge_token` |
| 401 | `challenge_expired` | `auth/twoFactorChallenge`, `auth/twoFactorRecovery` |
| 401 | `invalid_two_factor_code` | `auth/twoFactorChallenge` (в `profile/twoFactorConfirm` — 422) |
| 401 | `invalid_recovery_code` | `auth/twoFactorRecovery` |
| 403 | `impersonation_disabled`, `already_impersonating` | `auth/startImpersonation` |
| 400 | `no_active_impersonation`, `impersonator_not_found` | `auth/stopImpersonation` |
| 422 | `two_factor_not_initialised` | `profile/twoFactorConfirm` до `twoFactorEnable` |
| 404 | `sanctum_unavailable` | `profile/token*`, когда laravel/sanctum не установлен или у модели пользователя нет `HasApiTokens` |
| 422 | `unsupported_locale`, `unsupported_theme` | `system/setLocale`, `system/setTheme` |

Ограничение частоты (429) отдаёт middleware `ThrottleRequests` до входа в action, поэтому тело такого ответа формирует обработчик исключений приложения, а не конверт панели.

## 6. Права доступа

Права проверяет middleware `Dskripchenko\LaravelAdmin\Permission\Middleware\AdminAccess`, навешенный на action в `getMethods()`:

```php
'middleware' => [AdminAccess::class.':admin.users.view'],
'middleware' => [AdminAccess::class.':admin.users.view;admin.system.audit.view'], // И: нужны все
```

Пользователь проверяется через `hasAccess($permission)` на guard'е текущей панели. Нет пользователя — 401 `unauthenticated`, нет права — 403 `forbidden`.

Автоматически навешиваемые права:

| Что | Право |
|---|---|
| ресурс (`Resource::permission()`, по умолчанию `admin.{slug}`) | `.view` — meta, search, summary, read, export, action, tree, экраны list/tree/view, listener, сохранённые представления; `.create` — create, createScreen; `.update` — update, inlineUpdate, editScreen; `.delete`, `.restore`, `.force-delete`, `.replicate`, `.reorder` — одноимённые action'ы |
| настройки (`SettingsResource::permission()`, по умолчанию `admin.settings.{slug}`) | `.view` — meta, read; `.update` — update |
| экран | `Screen::permission()` (строка или список — все обязательны) на state, runMethod, listener; без `permission()` экран доступен любому вошедшему пользователю |

Кроме middleware, действия проверяются по месту запуска (`Action\ActionLocator`): действие можно выполнить, только если оно видно пользователю — выполняется его `canSee()`, у пользователя есть его `permission()`, и то же верно для выпадающего меню и layout'а, в которых оно лежит. Иначе ответ 403 `action_forbidden` с сообщением, называющим недостающее право, если оно известно. Это касается:

- `{slug}/action` — действия ресурса из `actions()`;
- `{slug}/runMethod` экрана — если метод привязан к кнопке/модальному действию (`method($name)`) в command bar или layout'е экрана;
- `delayed/run` — асинхронного обработчика: сначала проверяется право, с которым пара `entity`/`method` внесена в allowlist, затем `AsyncAction`'ы, которые этот обработчик запускают.

Скрытие кнопки в интерфейсе поэтому никогда не единственная защита: запрос, называющий скрытое действие, отклоняется на сервере.

## 7. Пагинация и фильтры (`{slug}/search`)

Запрос — `POST /api/admin/{slug}/search`:

```json
{
  "page": 1,
  "per_page": 25,
  "q": "ivan",
  "filters": { "email": "ivan", "created_at": { "from": "2026-01-01", "to": "2026-02-01" } },
  "order": [{ "column": "created_at", "direction": "desc" }],
  "group_by": "status"
}
```

- `per_page` по умолчанию `admin.pagination.default_per_page` (25), не больше `admin.pagination.max_per_page` (100).
- `filters` разбирает `Filter\HttpFilterParser`. Поддерживаются три формы: карта `{column: value}`, список `[{column, value}]` и объект-диапазон `{column: {from, to}}`. Ключ `operator` в списочной форме игнорируется: как значение применяется к запросу, решает фильтр ресурса с этим полем (`Resource::filters()`). Значения для полей без объявленного фильтра не применяются.
- `q` — свободный текст: `LIKE %q%` по `searchableFields()` ресурса.
- `order` — список `{column, direction}`; без него — колонка ручной сортировки (для `reorderable()` ресурсов) или `defaultOrder()`.
- `ids` — вернуть только записи с этими ключами (так ResourcePicker подгружает выбранные значения).

Ответ:

```json
{
  "success": true,
  "payload": {
    "data": [ /* записи */ ],
    "meta": {
      "page": 1, "per_page": 25, "total": 1234, "last_page": 50,
      "from": 1, "to": 25,
      "summary": null,
      "groups": null
    }
  }
}
```

`meta.groups` — при `group_by`: `[{value, count}]` по всему отфильтрованному набору, без пагинации. Курсорной пагинации нет.

## 8. Ограничение частоты

| Где | Лимит | Конфиг |
|---|---|---|
| весь API панели (на пользователя) | `240,1` | `admin.api.throttle` |
| `auth/login`, `auth/twoFactorChallenge`, `auth/twoFactorRecovery` | `5,1`, ключ `auth-{panel}` | `admin.auth.login_throttle` |
| `auth/forgotPassword` | `3,5`, ключ `forgot-{panel}` | — |
| `auth/resendEmailVerification` | `3,1`, ключ `verify-{panel}` | — |

У именованных лимитов свой префикс ключа, поэтому их счётчики не делятся с общим лимитом API. Превышение — `429` с `Retry-After`.

## 9. Docblock'и action'ов

OpenAPI-спецификация строится из docblock'ов. В коде ядра используются теги:

```php
/**
 * Краткое описание (первая строка — заголовок операции).
 *
 * @input  string(email) $email
 * @input  boolean ?$remember          // ? — необязательное поле
 * @input  [operationSchema]           // поля, известные только в рантайме
 *
 * @output object $payload
 * @output string $payload.redirect_url
 *
 * @header string ?$If-None-Match The ETag of the previous response.
 *
 * @security AdminSession
 * @security AdminBearer
 *
 * @response 200 {LoginResponse}
 * @response 422 {ValidationErrorResponse}
 */
```

- `@input [operationSchema]` — поля ресурса: laravel-api вызывает `ResourceController::operationSchema(OperationContext $operation)`, и тот строит схему из полей и правил валидации того ресурса, чей маршрут описывается.
- `@security` — одна из двух схем, объявленных в `AdminApi::getOpenApiSecurityDefinitions()`: `AdminSession` (cookie сессии, имя из `session.cookie`) и `AdminBearer` (bearer-токен из профиля). Несколько тегов означают «подходит любая». Публичные action'ы (`auth/login` и т.п.) `@security` не указывают.
- `@response {Name}` ссылается на шаблон из `getOpenApiTemplates()` — см. [schemas.md](schemas.md). Два `@response` с одним кодом не поддерживаются: второй молча заменяет первый.
- Проверить разметку можно командой `php artisan api:lint --api-version=admin`.

## 10. Локаль

Локаль запроса выставляет middleware `AdminLocale` через `Theme\LocaleResolver`. Порядок источников:

1. `?locale=` в query;
2. заголовок `X-Admin-Locale`;
3. `locale` пользователя;
4. cookie `admin_locale`;
5. `Accept-Language`;
6. `admin.ui.default_locale`, а если он не задан — локаль приложения.

Берётся первое значение из `admin.ui.available_locales`. Сообщения (`payload.message`, тексты манифеста) переводятся в выбранную локаль.

## 11. Тестирование

Тесты обращаются к action'ам по полному пути:

```php
$this->actingAsAdmin([], ['admin.users.create']);

$this->postJson('/api/admin/users/create', ['name' => 'X', 'email' => 'x@example.com'])
    ->assertStatus(201)
    ->assertJsonPath('success', true);
```

Помощники из `Testing\Concerns`: `ActsAsAdmin` (`actingAsAdmin($attributes, $permissions)`, `actingAsSuperAdmin()`) и `InteractsWithAdminResources` (`getResourceMeta`, `postResourceSearch`, `getResourceRead`, `postResourceCreate`, `postResourceUpdate`, `postResourceDelete`, `postResourceAction`, `assertResourceMetaOk`, `assertResourceCount`). Подробнее — [../testing.md](../testing.md).

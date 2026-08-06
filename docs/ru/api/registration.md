# API: регистрация в `AdminApi::getMethods()`

Документ описывает, как admin-API регистрируется через `dskripchenko/laravel-api`: структуру `AdminApi`/`AdminApiModule`, динамическую регистрацию Resource-контроллеров через `ResourceCompiler`, наполнение middleware-каскада и Schema-templates для `@response`.

> Глобальные правила (URL-паттерн, docblock'и, security) — в [conventions.md](conventions.md). Конкретные actions — в соседних файлах.

---

## 1. AdminApiModule и AdminApi

```php
namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\BaseModule;

final class AdminApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'admin' => AdminApi::class,
        ];
    }
}
```

```php
namespace Dskripchenko\LaravelAdmin\Http;

use Dskripchenko\LaravelApi\BaseApi;

class AdminApi extends BaseApi
{
    public static function getMethods(): array
    {
        return array_merge_deep(
            self::staticControllers(),
            self::resourceControllers(),
            self::screenControllers(),
            self::pluginControllers(),
        );
    }

    private static function staticControllers(): array
    {
        return [
            'middleware' => [
                \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
            ],
            'controllers' => [
                'system'  => [/* SystemController + actions */],
                'auth'    => [/* AuthController + actions */],
                'profile' => [/* ProfileController + actions */],
                'uploads' => [/* UploadController + actions */],
                'delayed' => [/* DelayedController + actions */],
            ],
        ];
    }

    private static function resourceControllers(): array
    {
        $controllers = [];
        foreach (app(ResourceRegistry::class)->all() as $compiled) {
            $controllers[$compiled->slug()] = [
                'controller' => $compiled->controllerClass(),
                'middleware' => [
                    \Dskripchenko\LaravelAdmin\Http\Middleware\AdminAuth::class,
                ],
                'actions'    => $compiled->actionsMap(),
            ];
        }
        return ['controllers' => $controllers];
    }

    private static function screenControllers(): array { /* аналогично */ }
    private static function pluginControllers(): array { /* собирает из AdminPlugin'ов */ }
}
```

В `AdminServiceProvider::boot()`:

```php
// API живёт ОТДЕЛЬНО от SPA-shell. SPA — под config('admin.path') (default 'admin').
// API — под config('admin.api_path') (default 'api/admin'), без вложенности под path.
Route::group([
    'prefix'     => config('admin.api_path'),
    'middleware' => config('admin.middleware.api'),
    'as'         => 'admin.api.',
], function (): void {
    (new AdminApiModule())->register('admin');
});
```

> **Замечание про `api/{version}/...` паттерн laravel-api.** Стандартный laravel-api ожидает URL `api/{version}/{controller}/{action}`. У нас `version = 'admin'` (не настоящая версия — внутренний идентификатор, см. ARCHITECTURE.md п.12.4 и п.13.12: семвер admin = семвер API без exposed-версии). Финальный URL получается `{api_path}/{controller}/{action}` = `/api/admin/{controller}/{action}` — где `api/admin` — это `api_path` целиком, а сегменты после — `{controller}/{action}`. Если в будущем понадобятся параллельные версии, добавим вторую запись в `getApiVersionList()` (например, `'admin-v2' => AdminApiV2::class`).

---

## 2. Динамическая регистрация Resource-контроллеров

Каждый зарегистрированный Resource (`Admin::resources([UserResource::class])`) превращается в один controller под slug = `Resource::slug()`.

`ResourceCompiler` создаёт класс контроллера на лету через runtime-bind (или генерирует один раз стабильный controller-класс с маршрутизацией внутрь Resource):

```php
namespace Dskripchenko\LaravelAdmin\Resource;

final class ResourceController extends \Dskripchenko\LaravelApi\Http\Controllers\CrudController
{
    public function __construct(private readonly Resource $resource) {}

    public function service(): CrudServiceInterface
    {
        return new ResourceCrudService($this->resource);
    }

    /**
     * Получить метаданные ресурса (поля, фильтры, колонки).
     *
     * @output object $payload Метаданные.
     * @output array  $payload.fields Список полей формы.
     * @output array  $payload.columns Список колонок таблицы.
     * @output array  $payload.filters Список фильтров.
     * @security AdminSession
     * @security AdminBearer
     * @response 200 {ResourceMeta}
     */
    public function meta(): JsonResponse { /* унаследован из CrudController */ }

    // ... search, read, create, update, delete унаследованы
    // restore, forceDelete, replicate, inlineEdit, view, audit, reactiveField,
    // reorder, relations*, views*, preferences* — переопределены в Resource
}
```

`ResourceCompiler::actionsMap()` возвращает массив action-name → method-config с правильными HTTP-методами:

```php
return [
    'meta'        => ['method' => ['get']],
    'search'      => ['method' => ['post'], 'middleware' => [AdminAccess::class . ':' . $resource::permission() . '.view']],
    'read'        => ['method' => ['get'],  'middleware' => [...]],
    'create'      => ['method' => ['post'], 'middleware' => [...]],
    'update'      => ['method' => ['post'], 'middleware' => [...]],     // POST, не PATCH/PUT — конвенция laravel-api
    'delete'      => ['method' => ['post']],
    'restore'     => ['method' => ['post']],
    'forceDelete' => ['method' => ['post']],
    'replicate'   => ['method' => ['post']],
    'inlineEdit'  => ['method' => ['post']],
    'view'        => ['method' => ['get']],
    'audit'       => ['method' => ['get']],
    'reactiveField' => ['method' => ['get']],
    'reorder'     => ['method' => ['post']],
    'relationsList'   => ['method' => ['get']],
    'relationsAttach' => ['method' => ['post']],
    'relationsDetach' => ['method' => ['post']],
    'relationsSync'   => ['method' => ['post']],
    'viewsList'      => ['method' => ['get']],
    'viewSave'       => ['method' => ['post']],
    'viewUpdate'     => ['method' => ['post']],
    'viewDelete'     => ['method' => ['post']],
    'viewApply'      => ['method' => ['post']],
    'preferencesGet' => ['method' => ['get']],
    'preferencesSet' => ['method' => ['post']],
    'export'         => ['method' => ['post']],
    'exportStatus'   => ['method' => ['get']],
    'importUpload'   => ['method' => ['post']],
    'importPreview'  => ['method' => ['post']],
    'importRun'      => ['method' => ['post']],
    'importCancel'   => ['method' => ['post']],
    'bulkAction'     => ['method' => ['post']],
    'singleAction'   => ['method' => ['post']],
    'actionParameters' => ['method' => ['get']],
];
```

> **Конвенция:** в `laravel-api` все mutation-actions используют `POST`, не `PATCH`/`PUT`/`DELETE`. URL-паттерн `{controller}/{action}` не несёт REST-семантики — semantics несёт **имя action'а**.

---

## 3. Screen-контроллеры

Аналогично Resource. Каждый зарегистрированный Screen становится контроллером со slug = `Str::kebab(class_basename(ScreenClass))`:

```php
final class CompiledScreenController extends ApiController
{
    public function __construct(private readonly Screen $screen) {}

    public function state(Request $request): JsonResponse { /* */ }
    public function runMethod(Request $request): JsonResponse { /* любой command-метод Screen'а */ }
    public function async(Request $request): JsonResponse { /* reactive layer reload */ }
}
```

actions:

```php
'state'     => ['method' => ['get']],
'runMethod' => ['method' => ['post']],
'async'     => ['method' => ['get']],
```

Имя метода Screen'а передаётся в body: `{ "method": "save", "state": {...}, "parameters": {...} }`.

---

## 4. Schema-templates для @response

Каждый named template (`{XxxResponse}`) объявляется через **`getOpenApiTemplates(): array`** на классе версии API. На `AdminApi` (наследнике `BaseApi`) выставлено `public static bool $useResponseTemplates = true;` — laravel-api без этого флага шаблоны не считает.

Структура: метод возвращает map `'TemplateName' => ['field' => 'type-spec', ...]`. Type-spec поддерживает синтаксис:

| Pattern | Значение |
|---------|----------|
| `'string!'` | required |
| `'string'` | optional |
| `'string(date-time)'` | с форматом (формат как у OpenAPI: `email`, `uuid`, `date-time`, `int64`, ...) |
| `'@RefName'` | ссылка на другой template (через `components/schemas` в OpenAPI) |
| `'@RefName[]'` | массив ссылок |

Пример:

```php
public static function getOpenApiTemplates(): array
{
    return [
        'AdminUserSummary' => [
            'id'                => 'integer!',
            'name'              => 'string!',
            'email'             => 'string(email)!',
            'avatar'            => 'string',
            'twoFactorEnabled'  => 'boolean!',
            'impersonator'      => '@ImpersonatorRef',
        ],
        'ImpersonatorRef' => [
            'id'   => 'integer!',
            'name' => 'string!',
        ],
        'LoginResponse' => [
            'success' => 'boolean!',
            'payload' => '@LoginPayload',
        ],
        'LoginPayload' => [
            'user'         => '@AdminUserSummary',
            'redirect_url' => 'string!',
        ],
    ];
}
```

В контроллере ссылка на template — стандартным `@response`:

```php
/**
 * @response 200 {LoginResponse}
 * @response 401 {InvalidCredentialsResponse}
 */
```

### Структура файлов в admin

Все templates admin core живут в `src/Http/Schemas/` как traits, подключаемые в `AdminApi`:

```
src/Http/
├── AdminApi.php                              # extends BaseApi, use traits, $useResponseTemplates=true
├── AdminApiModule.php                        # extends BaseModule, getApiVersionList()
└── Schemas/
    ├── AdminApiCommonSchemas.php             # envelope, errors, building blocks (AdminUserSummary, FieldSchema, ColumnSchema, ...)
    ├── AdminApiSystemSchemas.php             # system + auth + profile templates
    ├── AdminApiResourceSchemas.php           # resource controllers + actions + settings
    ├── AdminApiUiSchemas.php                 # screens, dashboards, uploads, delayed, exports/imports
    └── AdminApiSisterPackSchemas.php         # search, health (sister-packs могут перекрыть/дополнить через AdminPlugin)
```

`AdminApi::getOpenApiTemplates()` объединяет всё через `array_merge()` от пяти `provide*Schemas()` методов traits.

Полный человекочитаемый список templates — в [schemas.md](schemas.md).

### Sister-packs и templates

Sister-pack может предоставить свои templates через `AdminPlugin`-контракт:

```php
final class AdminMediaPlugin implements AdminPlugin
{
    public function openApiTemplates(): array
    {
        return [
            'MediaItemResponse' => [/* ... */],
            // ...
        ];
    }
}
```

`AdminApi::getOpenApiTemplates()` после своего `array_merge` дополнительно мерджит вклад от всех зарегистрированных plugin'ов.

---

## 5. Security schemes

Регистрируются в `config/laravel-api.php` (через наш AdminApi-публикатор):

```php
'security_schemes' => [
    'AdminSession' => [
        'type' => 'apiKey',
        'in'   => 'cookie',
        'name' => 'laravel_session',
    ],
    'AdminBearer' => [
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT',          // условный label, фактически — Sanctum opaque token
    ],
    'Public' => [
        'type' => 'apiKey',
        'in'   => 'header',
        'name' => 'X-No-Auth',
    ],
],
```

`@security AdminSession` или `@security AdminBearer` ссылается на эти определения.

Public-actions (`auth.login`, `auth.forgotPassword`, `auth.resetPassword`) указывают `@security Public`.

---

## 6. Middleware каскад

| Уровень | Где задаётся | Когда применяется |
|---|---|---|
| Global (Laravel) | `config('admin.middleware.api')` | на весь `Route::group` |
| Module | `AdminApiModule::middleware()` | на весь `/api/admin/*` |
| Api version | `AdminApi::getMethods() → 'middleware'` | на все controllers версии |
| Controller | `getMethods() → 'controllers' → {slug} → 'middleware'` | на все actions controller'а |
| Action | `getMethods() → 'controllers' → {slug} → 'actions' → {name} → 'middleware'` | на конкретный action |

Каждый уровень может **исключать** middleware верхнего уровня:

```php
'login' => [
    'method' => ['post'],
    'exclude-middleware' => [AdminAuth::class],     // публичный, не требует auth
    'middleware'         => [ThrottleRequests::class . ':5,1'],
],
```

---

## 7. Команды artisan

`laravel-api` поставляет команды; в admin делаем aliases для удобства:

| Команда | Описание |
|---|---|
| `php artisan admin:api:routes` | вывести все зарегистрированные admin-actions с URL и методами |
| `php artisan admin:api:openapi` | сгенерировать `openapi.json` |
| `php artisan admin:api:client` | сгенерировать TypeScript-интерфейсы для SPA |
| `php artisan admin:api:postman` | сгенерировать Postman Collection |
| `php artisan admin:api:http` | сгенерировать `.http`-files (для VS Code REST Client / IntelliJ) |

---

## 8. Тестирование

```php
// tests/Feature/HelloResourceTest.php
beforeEach(function () {
    Admin::resources([UserResource::class]);
});

it('lists users via search action', function () {
    $this->actingAsAdmin($admin, ['admin.users.view'])
        ->postJson('/api/admin/users/search', [
            'page' => 1, 'per_page' => 25,
            'filters' => [['column' => 'is_active', 'operator' => '=', 'value' => true]],
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['payload' => ['data', 'meta']]);
});
```

`ResourceTestCase` оборачивает в helper'ы:

```php
$this->callResourceAction('users', 'search', [
    'filters' => [['column' => 'is_active', 'operator' => '=', 'value' => true]],
]);
```

---

## 9. Запрет на отступления

В **code-review** проверяется:

1. Все actions объявлены в `getMethods()` (нет «свободных» Route::post вне laravel-api).
2. У каждого action есть полный docblock (`@input`/`@output`/`@security`/`@response`).
3. Все actions возвращают envelope `{success, payload}` через `$this->success()` / `$this->error()`.
4. URL не содержит path-параметров кроме `{controller}/{action}`.
5. Permissions проверяются либо в middleware, либо явно через `$this->authorize(...)`.

CI-job `php artisan admin:api:lint` проверит это автоматически (планируется на P3 поверх стандартного lint'а laravel-api).

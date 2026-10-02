# API: регистрация в `AdminApi::getMethods()`

Как API панели подключается к `dskripchenko/laravel-api`: модуль и версии, статические контроллеры, генерация контроллеров ресурсов, настроек и экранов, дополнительные панели, каскад middleware, security schemes и шаблоны ответов для OpenAPI.

> Общие правила (URL, конверт, ошибки, права) — в [conventions.md](conventions.md). Конкретные action'ы — в соседних файлах.

---

## 1. Модуль и версия API

`AdminServiceProvider` подменяет модуль laravel-api своим: `$this->app->singleton('api_module', AdminApiModule::class)`. Маршруты регистрирует сам laravel-api (`ApiServiceProvider::makeApiRoutes()`): для каждого action'а каждой версии — именованный маршрут `api.{version}.{controller}.{action}`, плюс общий маршрут `api-endpoint` по шаблону `{version}/{controller}/{action}`. Отдельного `Route::group` для API в ядре нет; `routes/admin.php` содержит только SPA-shell.

`AdminApiModule` (`src/Http/AdminApiModule.php`) переопределяет у `BaseModule`:

| Метод | Что возвращает |
|---|---|
| `getApiVersionList()` | `['admin' => AdminApi::class]` + по версии на каждую дополнительную панель (`admin.panels.{id}.api`) |
| `getApiPrefix()` | `config('laravel-api.prefix', 'api')` |
| `getApiUriPattern()` | `config('laravel-api.uri_pattern', '{version}/{controller}/{action}')` |
| `getApiMiddleware()` | `[CaptureApiRequest::class, RunVersionMiddleware::class]` — см. §6 |

`AdminApi` (`src/Http/AdminApi.php`) — наследник `BaseApi`:

```php
class AdminApi extends BaseApi
{
    use AdminApiCommonSchemas, AdminApiResourceSchemas, AdminApiSisterPackSchemas,
        AdminApiSystemSchemas, AdminApiUiSchemas;

    public static $useResponseTemplates = true;

    public static function panelId(): string { return 'admin'; }

    public static function getMethods(): array
    {
        $controllers = [ /* статические контроллеры, §2 */ ];

        $controllers = array_merge($controllers, (new ResourceCompiler)->compile(app(ResourceRegistry::class), static::panelId()));
        $controllers = array_merge($controllers, (new SettingsCompiler)->compile(app(SettingsRegistry::class), static::panelId()));
        $controllers = array_merge($controllers, (new ScreenCompiler)->compile(app(ScreenRegistry::class), static::panelId()));

        return [
            'middleware' => [ThrottleRequests::class.':'.config('admin.api.throttle', '240,1')],
            'controllers' => $controllers,
        ];
    }
}
```

Результат кэшируется laravel-api в статическом свойстве; после изменения реестров (в тестах — после `Resources::add` / `Resources::clear`) кэш сбрасывают `AdminApi::clearCache()`. Ядро делает это само в конце `boot()`, после загрузки плагинов.

## 2. Статические контроллеры

| Ключ | Контроллер | Actions (метод) |
|---|---|---|
| `system` | `Http\Controllers\SystemController` | `bootstrap`¹, `manifest`, `me`, `menu`, `search`, `locales`¹, `permissions`, `plugins`, `status`, `theme`¹ — GET; `setLocale`¹, `setTheme`¹ — POST |
| `auth` | `Auth\Controllers\AuthController` | все POST: `login`¹, `logout`, `forgotPassword`¹, `resetPassword`¹, `verifyEmail`¹, `resendEmailVerification`¹, `twoFactorChallenge`¹, `twoFactorRecovery`¹, `startImpersonation`, `stopImpersonation` |
| `profile` | `Profile\Controllers\ProfileController` | GET: `show`, `twoFactorStatus`, `tokensList`; POST: `update`, `changePassword`, `twoFactorEnable`, `twoFactorConfirm`, `twoFactorDisable`, `twoFactorRegenerateCodes`, `tokenCreate`, `tokenRevoke` |
| `dashboard` | `Widget\DashboardController` | GET: `get`, `widgets`; POST: `save`, `savePeriod`, `reset` |
| `audit` | `Audit\AuditController` | GET: `list`, `timeline` |
| `delayed` | `DelayedProcess\DelayedProcessController` | POST: `run`; GET: `status` |
| `import` | `Import\ImportController` | POST: `upload`, `preview`, `start`; GET: `status` |
| `uploads` | `Uploads\UploadController` | POST: `upload`, `image`; GET: `serve` |
| `notifications` | `Notifications\NotificationController` | GET: `list`, `unread`; POST: `markAsRead`, `markAllAsRead`, `destroy` |

¹ — публичный action: `'exclude-middleware' => [AdminAuth::class]`, вход не требуется.

Собственные лимиты частоты у `auth/login`, `auth/twoFactorChallenge`, `auth/twoFactorRecovery` (`admin.auth.login_throttle`, ключ `auth-{panel}`), `auth/forgotPassword` (`3,5`, ключ `forgot-{panel}`) и `auth/resendEmailVerification` (`3,1`, ключ `verify-{panel}`).

## 3. Контроллеры ресурсов (`ResourceCompiler`)

Каждый ресурс панели становится контроллером с ключом `Resource::slug()` (по умолчанию — имя класса без суффикса `Resource`, во множественном числе, в kebab-case: `UserResource` → `users`). Все ресурсы обслуживает один класс `Resource\ResourceController`: какой ресурс нужен, он узнаёт из ключа контроллера (`ApiRequest::getApiControllerKey()`). На каждый action навешивается `AdminAccess` с правом от `Resource::permission()` (по умолчанию `admin.{slug}`):

| Action | Метод | Право | Регистрируется |
|---|---|---|---|
| `meta` | GET | `.view` | всегда |
| `search` | POST | `.view` | всегда |
| `summary` | POST | `.view` | всегда |
| `read` | GET | `.view` | всегда |
| `create` | POST | `.create` | всегда |
| `update` | POST | `.update` | всегда |
| `inlineUpdate` | POST | `.update` | всегда |
| `delete` | POST | `.delete` | всегда |
| `export` | GET, POST | `.view` | всегда |
| `action` | POST | `.view` | всегда; диспетчер действий ресурса, тело `{key, ids?, payload?}` — [actions.md](actions.md) |
| `listScreen`, `viewScreen` | GET | `.view` | всегда |
| `createScreen` | GET | `.create` | всегда |
| `editScreen` | GET | `.update` | всегда |
| `restore` | POST | `.restore` | модель с `SoftDeletes` |
| `forceDelete` | POST | `.force-delete` | модель с `SoftDeletes` |
| `replicate` | POST | `.replicate` | `replicable()` |
| `reorder` | POST | `.reorder` | `reorderable()` |
| `tree`, `treeScreen` | POST, GET | `.view` | `hierarchyParentKey()` не `null` |
| `listener` | POST | `.view` (право на create/update проверяет сам action) | в форме создания или редактирования есть `Listener` |

Если у ресурса включены сохранённые представления (`savedViews()`), добавляется контроллер `{slug}_views` (`Table\SavedViewsController`) с action'ами `list` (GET), `create`, `update`, `delete` (POST) — все под правом `.view`.

## 4. Контроллеры настроек (`SettingsCompiler`)

Каждый `SettingsResource` — контроллер `settings_{slug}` (подчёркивание, потому что точка в маршрутах Laravel требует отдельного ограничения), класс `Settings\SettingsController`:

| Action | Метод | Право |
|---|---|---|
| `meta` | GET | `{permission}.view` |
| `read` | GET | `{permission}.view` |
| `update` | POST | `{permission}.update` |

`SettingsResource::permission()` по умолчанию — `admin.settings.{slug}`.

## 5. Контроллеры экранов (`ScreenCompiler`)

Каждый зарегистрированный экран — контроллер с ключом `Screen::slug()` (по умолчанию — имя класса без суффикса `Screen` в kebab-case), класс `Screen\ScreenController`:

| Action | Метод | Назначение |
|---|---|---|
| `state` | GET | состояние, layout, command bar и мета экрана |
| `runMethod` | POST | вызов метода экрана; имя метода — в поле `method` тела |
| `listener` | POST | перерисовка одного `Listener`-layout'а экрана |

Если экран объявляет `permission()` (строка или список — нужны все), на все три action'а навешивается `AdminAccess`. Из этого конвейера исключены наследники `GeneratedScreen` (их обслуживает `ResourceController`) и `DashboardScreen` (их обслуживает `DashboardController`). Подробнее — [screens.md](screens.md).

## 6. Каскад middleware

| Уровень | Где задаётся | Применяется |
|---|---|---|
| Модуль | `AdminApiModule::getApiMiddleware()` | ко всем маршрутам модуля; только `CaptureApiRequest` и `RunVersionMiddleware` |
| Стек панели | `config('admin.middleware.api')`: `web`, `CaptureApiRequest`, `AdminAuth`, `RunActionMiddleware`, `AdminLocale` | к версиям-панелям (`admin` и `admin.panels.*`); запускает `RunVersionMiddleware` на каждом запросе |
| Версия | `getMethods()['middleware']` | ко всем контроллерам версии (у `AdminApi` — общий `ThrottleRequests`; у `PanelApi` к нему добавляются `admin.panels.{id}.middleware.api`) |
| Контроллер | `getMethods()['controllers'][$key]['middleware']` | ко всем action'ам контроллера |
| Action | `getMethods()['controllers'][$key]['actions'][$name]['middleware']` | к одному action'у (например, `AdminAccess` ресурсов) |

Middleware контроллера и action'а laravel-api вешает на именованный маршрут; `RunActionMiddleware` дозапускает то, чего нет в стеке маршрута (например, когда запрос пришёл через общий маршрут `api-endpoint`), не запуская ничего дважды.

Контроллер или action может исключить middleware верхних уровней:

```php
'login' => [
    'method' => ['post'],
    'middleware' => [ThrottleRequests::class.':5,1,auth-admin'],
    'exclude-middleware' => [AdminAuth::class],   // публичный action
],
```

`AdminAuth` читает `exclude-middleware` текущего контроллера и action'а из `getPreparedMethods()` версии запроса и пропускает такие запросы без проверки входа.

### Хост-модуль со своими версиями

`AdminApiModule` открыт для наследования: хост-модуль может добавить версии приложения рядом с панелями.

```php
final class AppApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return [
            ...parent::getApiVersionList(),   // admin + панели
            'v1' => Api\V1::class,            // публичный API приложения
        ];
    }
}
```

Стек панели (`admin.middleware.api`: сессия, CSRF, `AdminAuth`) на такие версии не распространяется: `RunVersionMiddleware` выбирает стек на каждом запросе. Для версии-панели — стек панели, для остальных — только глобальные, контроллерные и action-middleware из `getMethods()` её класса (через `RunActionMiddleware`). Выбор делается не при регистрации маршрутов: группа middleware у laravel-api одна на модуль и собирается один раз при boot, а под Octane воркер загружается однажды.

Если версии приложения нужна локаль панели или сессия, она объявляет это сама, например `'middleware' => [AdminLocale::class]` в своём `getMethods()`.

## 7. Дополнительные панели (`PanelApi`)

Дополнительная панель объявляется в `admin.panels.{id}` и получает свою версию API `/api/{id}/...`. Класс версии — наследник `Panel\PanelApi`, указанный в `admin.panels.{id}.api`:

```php
final class ClientApi extends \Dskripchenko\LaravelAdmin\Panel\PanelApi {}

// config/admin.php
'panels' => [
    'client' => [
        'api' => App\Admin\ClientApi::class,
        'middleware' => ['api' => [SomePanelMiddleware::class]],
        // path, auth, plugins, ...
    ],
],
```

`PanelApi` наследует всю системную поверхность `AdminApi` (system, auth, profile, dashboard, uploads, notifications и т.д.), но:

- `panelId()` находит панель по `static::class` в `admin.panels.*.api`; один класс обслуживает ровно одну панель;
- ресурсы, настройки и экраны компилируются только те, что зарегистрированы для этой панели;
- `getPreparedMethods()` не сливает методы родительского класса, иначе в клиентскую панель попали бы ресурсы основной;
- `admin.panels.{id}.middleware.api` — **добавки** к общему стеку, они дописываются в `getMethods()['middleware']`;
- аутентификация работает на guard'е панели (`Panels::currentGuard()`).

## 8. Security schemes

Схемы объявлены в `AdminApi::getOpenApiSecurityDefinitions()`:

| Схема | Тип | Описание |
|---|---|---|
| `AdminSession` | `apiKey` в cookie, имя — `config('session.cookie')` | сессия браузера после входа; такие запросы проходят CSRF-проверку |
| `AdminBearer` | `http`, `bearer` | персональный API-токен из профиля (laravel/sanctum) |

Docblock action'а ссылается на них тегами `@security AdminSession` / `@security AdminBearer`; публичные action'ы `@security` не указывают.

## 9. Шаблоны ответов для `@response`

Named-шаблоны (`{XxxResponse}`) отдаёт `AdminApi::getOpenApiTemplates()`. laravel-api учитывает их только при `public static $useResponseTemplates = true;` — флаг выставлен на `AdminApi`.

Метод сливает результаты пяти трейтов из `src/Http/Schemas/`:

```php
public static function getOpenApiTemplates(): array
{
    return array_merge(
        self::provideCommonSchemas(),      // AdminApiCommonSchemas
        self::provideSystemSchemas(),      // AdminApiSystemSchemas
        self::provideResourceSchemas(),    // AdminApiResourceSchemas
        self::provideUiSchemas(),          // AdminApiUiSchemas
        self::provideSisterPackSchemas(),  // AdminApiSisterPackSchemas
    );
}
```

Шаблон — карта `'поле' => 'тип'`. Синтаксис типа:

| Запись | Значение |
|---|---|
| `'string!'` | обязательное поле |
| `'string'` | необязательное |
| `'string(date-time)'` | с OpenAPI-форматом (`email`, `uuid`, `date-time`, ...) |
| `'string! Описание'` | текст после типа — описание поля |
| `'@RefName'` | ссылка на другой шаблон |
| `'@RefName[]'` | массив ссылок |

Пример из `AdminApiCommonSchemas`:

```php
'AffectedResponse' => [
    'success' => 'boolean!',
    'payload' => '@AffectedPayload',
],
'AffectedPayload' => [
    'affected' => 'integer!',
    'message' => 'string! What was applied, in words',
],
```

В docblock'е action'а: `@response 200 {AffectedResponse}`. Полный реестр — [schemas.md](schemas.md).

Плагины (`AdminPlugin`) своих шаблонов в `AdminApi` не добавляют: контракт плагина — `name()`, `version()`, `register()`, `boot(Admin $admin)`, а API плагин получает через регистрацию ресурсов, экранов, настроек и виджетов, которые компилируются по §3–§5.

## 10. Тестирование

```php
it('lists users via search', function (): void {
    $this->actingAsAdmin([], ['admin.users.view']);

    $this->postJson('/api/admin/users/search', [
        'page' => 1,
        'per_page' => 25,
        'filters' => ['is_active' => true],
    ])
        ->assertOk()
        ->assertJsonStructure(['payload' => ['data', 'meta']]);
});
```

То же через помощник `InteractsWithAdminResources`: `$this->postResourceSearch('users', ['is_active' => true])`. Карту маршрутов и разметку docblock'ов проверяет `php artisan api:lint --api-version=admin` (команда laravel-api).

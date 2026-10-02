---
title: Подключение админки к существующему Laravel-приложению
audience: developer
status: stable
locale: ru
translated_from: en/integration.md
translated_at: 2026-10-02
---

# Подключение админки к существующему Laravel-приложению

[Быстрый старт](getting-started.md) ведёт от чистого приложения до
работающей админки. Эта страница — для приложения, у которого уже есть
пользователи, свой API, прокси перед ним и выкладка: что меняет установщик,
как пустить в админку существующих пользователей и как админка уживается с
остальным.

## 1. Требования и что меняет установщик

- PHP 8.2+, Laravel 11, 12 или 13.
- База, которую приложение уже мигрирует. Админка хранит состояние в своих
  таблицах и пользуется сессией Laravel (группа middleware `web`).
- Node **не** нужен, если не собирать фронтенд самостоятельно.

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install            # отдельная таблица admin_users (dedicated)
# или
php artisan admin:install --shared   # входят ваши пользователи (shared)
```

`admin:install` спрашивает перед каждым шагом, который что-то меняет; у
каждого вопроса есть флаг (`--no-migrate`, `--no-user`, `--no-composer-hook`,
`--force`), а с `-n` команда идёт без вопросов, с ответами по умолчанию.

Что появляется:

| Что | Где |
|---|---|
| Конфиг | `config/admin.php` |
| Миграции | `database/migrations/2026_01_01_0000*_*.php` (копии миграций пакета; Laravel выполняет каждое имя один раз) |
| Готовый фронтенд | `public/vendor/admin/` (`assets/` + `source-hash.txt`) |
| Composer-хук (по желанию) | `"@php artisan admin:publish --ansi"` в `scripts.post-update-cmd` файла `composer.json` |
| только `--shared` | блок `auth` в `config/admin.php`, направленный на ваш guard, и `database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php` |
| только `--custom-build` | `resources/js/admin.js`, вход в `laravel()` в `vite.config.js`, `assets.vite_manifest`/`vite_entry` в `config/admin.php` |

Таблицы, которые создаёт `migrate`: `admin_users`, `admin_password_resets`,
`admin_roles`, `admin_role_assignments`, `admin_saved_views`,
`admin_dashboard_layouts`, `admin_audit_logs`, `admin_settings`,
`admin_import_processes`. Две зависимости приносят свои миграции, и они тоже
выполняются: `dskripchenko/laravel-delayed-process` (`delayed_processes`) и
`dskripchenko/laravel-translatable` (`languages`, `translations`,
`content_blocks`, `pages`, `page_content_block`). Миграции пакета грузятся из
`vendor/`, даже если удалить опубликованные копии.

Установщик не трогает `config/auth.php`, ваши модели, маршруты и `.env`. В
стратегии dedicated guard `admin`, провайдер `admin_users` и password broker
`admin_users` добавляются в конфиг auth **во время выполнения** и только если
вы сами не объявили guard'ы с такими именами.

### Как откатить

```bash
# 1. Откатить таблицы админки (и, для --shared, колонки в users).
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-admin/database/migrations \
  --path=database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php
# Таблицы зависимостей — если больше ничто ваше их не использует:
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-delayed-process/databases/migrations \
  --path=vendor/dskripchenko/laravel-translatable/databases/migrations

# 2. Удалить файлы.
rm config/admin.php database/migrations/2026_01_01_0000*_*admin*.php
rm -r public/vendor/admin
# и строку "admin:publish" из post-update-cmd в composer.json

# 3. Удалить пакет.
composer remove dskripchenko/laravel-admin
```

`migrate:reset --path` откатывает только миграции из указанных путей и
пропускает остальные («Migration not found»), ваши таблицы остаются.

## 2. Кто входит: `dedicated` или `shared`

| | `dedicated` (по умолчанию) | `shared` |
|---|---|---|
| Где администраторы | `admin_users`, модель `Dskripchenko\LaravelAdmin\Models\AdminUser` | ваша таблица, например `users`, модель `App\Models\User` |
| Guard | `admin` (session), регистрирует пакет | ваш, например `web` |
| Сброс пароля | broker `admin_users`, таблица `admin_password_resets` | ваш broker, например `users` |
| Кого пускают в админку | любую активную запись `admin_users` | только пользователей хотя бы с одной ролью админки (или тех, кого пускает `canAccessAdmin()`) |
| Когда выбирать | бэк-офис, сотрудники которого не пользователи сайта | одна учётка на человека: сотрудники уже входят на сайт |

Если сомневаетесь — `dedicated`: он вообще не трогает ваших пользователей.
Всё дальше в этом разделе — про `shared`.

### Стратегия shared по шагам

Рецепт прогнан целиком на стоковом Laravel 13 с уже заполненной таблицей
`users`: вход через API, `system/me`, тема и язык, подключение 2FA и вход с
кодом, отказ обычному пользователю, вход в браузере.

**1. Установка с `--shared`:**

```bash
php artisan admin:install --shared
```

Команда читает ваш конфиг auth — `auth.defaults.guard`, провайдера этого
guard'а, модель провайдера и `auth.defaults.passwords` — и записывает их в
`config/admin.php`:

```php
'auth' => [
    'strategy' => env('ADMIN_AUTH_STRATEGY', 'shared'),
    'guard' => env('ADMIN_GUARD', 'web'),
    'provider' => env('ADMIN_PROVIDER', 'users'),
    'model' => \App\Models\User::class,
    'table' => 'admin_users',        // не трогать, см. ниже
    'password_broker' => 'users',
    // ...
],
```

Вручную — те же пять значений: `ADMIN_AUTH_STRATEGY=shared`,
`ADMIN_GUARD=web`, `ADMIN_PROVIDER=users` в `.env` (или в конфиге) и
`model` / `password_broker` в `config/admin.php` (env-ключей у них нет).

`auth.table` оставьте `admin_users`: собственная миграция пакета для
`admin_users` выполняется при любой стратегии и создаёт таблицу с этим
именем, так что `users` там уронит миграцию. В стратегии shared таблица
просто остаётся пустой.

**2. Колонки админки в таблице пользователей.** `--shared` публикует
`2026_01_01_000100_add_admin_columns_to_users_table.php` (или
`php artisan vendor:publish --tag=admin-shared-migrations`), `migrate` её
применяет. Миграция добавляет в таблицу модели `admin.auth.model` колонки,
пропуская уже существующие:

| Колонка | Зачем |
|---|---|
| `locale` `string(8)`, `theme` `string(16)` | выбор пользователя в панели; без них переключение темы или языка — 500 |
| `is_active` `boolean default true` | отключение учётки: `false` закрывает вход в админку и завершает открытые сессии админки |
| `last_login_at`, `last_login_ip` | пишутся при каждом входе в админку (если колонок нет — не пишутся) |
| `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` | TOTP-2FA админки |

Откат миграции удаляет эти колонки. Если какие-то из них были у вас и
раньше, уберите их из `down()`.

**3. Два трейта на модель:**

```php
use Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;

class User extends Authenticatable
{
    use HasAdminAccess, HasAdminTwoFactor, HasFactory, Notifiable;
    // ...
}
```

- `HasAdminAccess` — роли админки (`roles()` через `admin_role_assignments`,
  `assignRole()`, `revokeRole()`, `hasAccess()`, `getAllPermissions()`).
- `HasAdminTwoFactor` — шифрующие касты для колонок 2FA и
  `hasTwoFactorEnabled()`. Без него пользователь, включивший 2FA в профиле,
  всё равно входит без кода.

**4. Назначить администратора.** Существующего пользователя — по email:

```bash
php artisan admin:user alice@example.com --super
```

или создать нового: `php artisan admin:user "Alice" alice@example.com secret123 --super`.
Из кода: `$user->assignRole('super-admin')` (роль создаёт первый
`admin:user --super`) или любая своя роль, см. [раздел 6](#6-права-и-роли).

**5. Вход** — `/admin/login` с обычным паролем пользователя. Через API:

```bash
# Сначала сессионная cookie и cookie XSRF-TOKEN
curl -c jar -b jar -s -o /dev/null http://localhost:8000/admin
XSRF=$(grep XSRF-TOKEN jar | awk '{print $7}' | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

curl -c jar -b jar -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","password":"secret123"}' \
  http://localhost:8000/api/admin/auth/login
# {"success":true,"payload":{"user":{"id":1,...},"permissions":["*"],"redirect_url":"/admin"}}

curl -c jar -b jar -H 'Accept: application/json' http://localhost:8000/api/admin/system/me
# {"success":true,"payload":{"id":1,"name":"Alice",...}}
```

### Кого пускают

В стратегии shared пользователь сайта по умолчанию **не** администратор.
Вход отвечает пользователю без роли админки `403 {"errorKey":"forbidden"}`
(«You do not have access to the admin panel»), API админки отвечает так же на
его сайтовую сессию, а shell считает его гостем. Сама сайтовая сессия
остаётся нетронутой.

Чтобы решать самим — сотрудники по домену почты, по колонке, по gate —
определите на модели `canAccessAdmin()`; её ответ заменяет проверку ролей (и
действует в стратегии dedicated тоже):

```php
public function canAccessAdmin(string $panelId): bool
{
    return $this->is_staff;
}
```

Отказать во входе по своим причинам (приостановленная компания, истёкший
договор) модель может и через `isDisabledForLogin(): bool`; то же делает
`is_active` (или `enabled`), равный `false`.

### Один вход или два

С `ADMIN_GUARD=web` у админки и сайта один вход: вход в админку — это вход
на сайт, выход из админки — выход с сайта. Чтобы входы были раздельными при
той же таблице, объявите свой guard в `config/auth.php` и укажите его
админке:

```php
// config/auth.php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'admin' => ['driver' => 'session', 'provider' => 'users'],
],
```

```dotenv
ADMIN_AUTH_STRATEGY=shared
ADMIN_GUARD=admin
ADMIN_PROVIDER=users
```

Cookie сессии по-прежнему одна, у приложения; раздельно хранится только
состояние входа.

### Особенности стратегии shared

- `HasRoles` из `spatie/laravel-permission` тоже определяет `roles()`,
  `assignRole()` и `getAllPermissions()`, поэтому оба трейта на один класс не
  поставить. Вынесите админку в подкласс над той же таблицей и укажите его в
  `admin.auth.model` и в провайдере (с отдельным guard'ом, как выше):
  `class AdminAccount extends User { use HasAdminAccess, HasAdminTwoFactor; }`.
  Методы трейта перекрывают унаследованные.
- Laravel Fortify хранит свою 2FA в тех же колонках `two_factor_*`, но
  шифрует их вручную. На такую модель `HasAdminTwoFactor` не ставьте и 2FA из
  профиля админки не включайте.
- Ресурс «Пользователи» из `dskripchenko/laravel-admin-starter` редактирует
  `admin_users`; выключите его (`'resources' => ['users' => false]` в
  `config/admin-starter.php`) и управляйте пользователями своим ресурсом.
- Тестовый помощник `ActsAsAdmin` создаёт `AdminUser`. В shared-приложении
  создайте в тесте своего пользователя, выдайте роль `$user->assignRole(...)`
  и вызовите `$this->actingAs($user, 'web')`.

## 3. Путь, домен, API, сессии, прокси

| Настройка | По умолчанию | Что делает |
|---|---|---|
| `ADMIN_PATH` | `admin` | SPA живёт на `/{path}/*`; `''` монтирует её в корень |
| `ADMIN_DOMAIN` | нет | привязывает маршруты SPA к одному хосту, например `admin.example.com` |
| `laravel-api.prefix` | `api` | API живёт на `/{prefix}/admin/{controller}/{action}` |
| `ADMIN_API_PATH` | вычисляется | URL API, который получает SPA; не задавайте |

```dotenv
ADMIN_PATH=backoffice     # → /backoffice/login
```

**Путь API.** API админки — версия `dskripchenko/laravel-api`, поэтому он
живёт под префиксом laravel-api: `/api/admin/*` по умолчанию,
`/backend/admin/*` при `'prefix' => 'backend'` в `config/laravel-api.php`. SPA
берёт URL API из того же префикса, так что смена префикса переносит и то, и
другое. `ADMIN_API_PATH` нужен только если прокси переписывает путь, который
видит браузер; маршруты он не переносит.

**Отдельный домен.** `ADMIN_DOMAIN` ограничивает маршруты shell; маршруты API
к домену не привязаны, и SPA обращается к ним на том хосте, с которого
загружена. С `ADMIN_DOMAIN=admin.example.com` и `ADMIN_PATH=` админка — корень
этого хоста и не перекрывает основной сайт; префикс API в catch-all shell не
попадает.

**Сессии и cookie.** Админка пользуется сессией приложения
(`config/session.php`): группа `web` — первая и в `admin.middleware.shell`, и
в `admin.middleware.api`. Всё, что настроено для сайта — драйвер, время
жизни, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, —
действует и для админки. Если админка на своём поддомене и должна делить
вход с сайтом, задайте `SESSION_DOMAIN=.example.com`.

**CSRF.** Каждый вызов API админки проходит через `web`, включая проверку
CSRF. SPA сама шлёт `X-XSRF-TOKEN` из cookie `XSRF-TOKEN`. Скрипт, который
ходит в API с сессией, должен делать то же (см. пример `curl` выше); без
токена ответ — `419`. Машинным клиентам — персональные API-токены из профиля
(Sanctum, если установлен).

**За прокси или балансировщиком.** Shell передаёт SPA абсолютные URL,
построенные через `url()`. За прокси, который снимает TLS и которому не
доверяют, они получаются `http://…`, и браузер блокирует их как mixed
content. Доверьте прокси в `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');   // или адреса прокси
})
```

и задайте `APP_URL` публичным адресом.

**Ограничения частоты.** `ADMIN_LOGIN_THROTTLE` (`5,1` — пять попыток в
минуту) на вход и шаги 2FA, три письма сброса пароля за пять минут,
`ADMIN_API_THROTTLE` (`240,1`) на остальной API.

## 4. Несколько панелей

Одной панели — `admin` — хватает большинству приложений. Вторая нужна, когда
у второй аудитории должна быть своя поверхность: кабинет клиента рядом с
бэк-офисом, со своими пользователями, входом, меню и ресурсами.

У каждой панели свой путь, guard и модель пользователя, своя версия API
(`/api/{id}/*`), свои middleware и плагины. Панель по умолчанию строится из
ключей верхнего уровня `config/admin.php`, остальные перечисляются в
`admin.panels`:

```php
// config/admin.php
'panels' => [
    'client' => [
        'path' => 'cabinet',                         // SPA на /cabinet
        'auth' => [
            'strategy' => 'dedicated',               // guard/provider/broker регистрируются сами
            'guard' => 'client',
            'provider' => 'client_users',
            'model' => App\Models\ClientUser::class, // HasAdminAccess, колонка `enabled` или `is_active`
            'table' => 'client_users',
            'password_broker' => 'client_users',
        ],
        'api' => App\Admin\ClientApi::class,         // API на /api/client/*
        'plugins' => [App\Admin\ClientPanelPlugin::class],
    ],
],
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Panel\PanelApi;

final class ClientApi extends PanelApi {}   // по подклассу на панель
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

final class ClientPanelPlugin implements AdminPlugin
{
    public function name(): string { return 'client-panel'; }
    public function version(): string { return '1.0.0'; }
    public function register(): void {}

    public function boot(Admin $admin): void
    {
        // Зарегистрированные здесь ресурсы принадлежат только панели `client`.
        $admin->resources([ProjectResource::class]);
        $admin->menu()->add(MenuNode::resource('projects'));
    }
}
```

Таблицу `client_users` и модель создаёте вы. Панель в корне
(`'path' => ''`) должна перечислить префиксы, которые ей нельзя
перехватывать: `'exclude_prefixes' => ['api', 'admin']`. Записи
`middleware.api` панели добавляются к общему стеку `admin.middleware.api`.

## 5. Свой модуль `dskripchenko/laravel-api`

laravel-api находит все версии API через одну привязку `api_module`. Админка
привязывает её к `Dskripchenko\LaravelAdmin\Http\AdminApiModule`, версии
которого — `admin` и панели. Если приложение привязывает свой модуль —
обычный `ApiServiceProvider extends
Dskripchenko\LaravelApi\Providers\ApiServiceProvider` с `getApiModule()`, —
ваш регистрируется позже и побеждает, и модуль, унаследованный от `BaseModule`
laravel-api, теряет API админки: `/api/admin/*` отвечает 404.

Наследуйтесь от `AdminApiModule` и добавляйте свои версии к списку родителя:

```php
namespace App\Api;

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;

class ApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return array_merge(parent::getApiVersionList(), [
            'v1' => V1Api::class,          // ваш BaseApi
        ]);
    }
}
```

```php
namespace App\Providers;

use App\Api\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseApiServiceProvider;

class ApiServiceProvider extends BaseApiServiceProvider
{
    protected function getApiModule()
    {
        return new ApiModule;
    }
}
```

Тогда `/api/v1/*` и `/api/admin/*` работают рядом.

- Не переопределяйте `getApiMiddleware()`. С 1.32.0 группа middleware модуля
  не зависит от версии, а стек выбирается на каждый запрос: админка и панели
  получают `admin.middleware.api` (сессия, CSRF, `AdminAuth`), ваши версии —
  только то, что объявляет их собственный `BaseApi::getMethods()`. Поэтому
  stateless-`v1` с bearer-токенами не получает от админки ни сессии, ни CSRF;
  если версии нужны сессия или `AdminLocale`, она объявляет их сама.
- `getApiPrefix()` и `getApiUriPattern()` читают `laravel-api.prefix` и
  `laravel-api.uri_pattern`, общие для всех версий. Смена префикса переносит и
  API админки, SPA следует за ним (см. [раздел 3](#3-путь-домен-api-сессии-прокси)).
- Не называйте версию так же, как панель (`admin` или ключ `admin.panels`).

## 6. Права и роли

Права — строки `admin.{domain}.{action}` с wildcard'ами (`admin.articles.*`,
`*`). Роли (`admin_roles`) хранят их списки и назначаются пользователям через
`admin_role_assignments`; ролей у пользователя может быть несколько. Каждый
ресурс получает `admin.{slug}.view/create/update/delete` (плюс restore,
replicate и т. д., если включены); screen'ы и настройки закрываются так же.
См. [Permissions](concepts/permissions.md).

```bash
php artisan admin:user --super                     # интерактивно; Super Admin = ['*']
php artisan admin:user alice@example.com --super   # выдать существующему пользователю
```

```php
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

$editor = Role::create([
    'name' => 'Editor',
    'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($editor);        // или ->assignRole('editor')
$user->hasAccess('admin.articles.update');   // true
$user->revokeRole('editor');
```

Интерфейс для ролей — в стартовом пакете:

```bash
composer require dskripchenko/laravel-admin-starter
```

Он регистрируется сам и добавляет ресурсы «Пользователи» (`admin_users`),
«Роли» (права выбираются из прав всех зарегистрированных ресурсов и
плагинов) и read-only «Журнал аудита». В стратегии shared «Пользователей»
выключите, см. выше.

## 7. Фронтенд

**Готовая сборка (по умолчанию).** Пакет поставляет SPA собранной;
`admin:install` копирует её в `public/vendor/admin`, shell её подключает.
Копию нужно обновлять после каждого обновления пакета — этим и занимается
composer-хук:

```json
"post-update-cmd": [
    "@php artisan admin:publish --ansi"
]
```

Если хук пропустили — добавьте его или выполняйте `php artisan admin:publish`
при выкладке после `composer install`. `public/vendor/admin` можно
коммитить или собирать в CI, как любой публичный ассет.

**Своя сборка** — для своих полей, виджетов, layout'ов и страниц на Vue:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

Shell тогда грузит вашу Vite-сборку (`assets.vite_manifest` /
`assets.vite_entry` в `config/admin.php`) вместо готовой копии. Держите
npm-пакет той же версии, что и composer-пакет. См.
[Frontend extension](frontend-extension.md).

## 8. Обновление и типичные проблемы

### Обновление

```bash
composer update dskripchenko/laravel-admin
php artisan migrate                  # новые миграции грузятся из vendor/
php artisan admin:publish            # делает composer-хук
php artisan config:clear             # если конфиг кешируется
```

Сначала прочитайте [CHANGELOG](../../CHANGELOG.md). Конфиг пакета сливается с
вашим на один уровень вглубь: новый ключ верхнего уровня `config/admin.php`
получает значение пакета, а новый ключ внутри опубликованного вами блока
(скажем, внутри `auth`) не появится, пока вы его не перенесёте. Если релиз
упоминает изменение конфига, сравните свой файл с
`vendor/dskripchenko/laravel-admin/config/admin.php`.

### Типичные проблемы

**Пустая страница или «The admin frontend was not found».** Shell не нашёл
фронтенд: `php artisan admin:publish` (готовая сборка) или `npm run build`
(своя; проверьте, что `assets.vite_manifest` указывает на существующий
манифест). 404 на `/vendor/admin/assets/*` значит, что `public/vendor/admin`
не доехал до сервера — выкладка без `public/vendor` или веб-сервер, у
которого корень не `public/`.

**Плашка «The admin files are out of date: run php artisan admin:publish».**
Опубликованная копия отличается от той, что в установленном пакете: пакет
обновили без переопубликования. Выполните `admin:publish` и добавьте
composer-хук, чтобы это не повторялось.

**401 сразу после успешного входа.** Сессионная cookie не возвращается:
- `SESSION_SECURE_COOKIE=true`, а админка открыта по HTTP;
- `SESSION_DOMAIN` не совпадает с хостом админки (или админка на поддомене, а
  домен не общий);
- драйвер сессий `array` или хранилище сессий/кеша, не общее для серверов;
- прокси без доверия, и запрос выглядит как HTTP (см.
  [раздел 3](#3-путь-домен-api-сессии-прокси)).
`401 {"errorKey":"session_expired"}` значит, что пароль пользователя сменили
после входа: войдите заново.

**403 «You do not have access to the admin panel».** Стратегия shared и
пользователь без роли админки — выдайте роль (`admin:user email --super`).
`403 account_inactive` — отключённая учётка (`is_active` или `enabled` равны
false, или так ответил `isDisabledForLogin()`).

**419 на вызовах API из скрипта.** В запросе нет CSRF-токена: пошлите
`X-XSRF-TOKEN` из cookie или используйте API-токен.

**Не тот язык.** Язык админки выбирается по порядку: `?locale=`, заголовок
SPA `X-Admin-Locale`, колонка `locale` пользователя, cookie `admin_locale`,
`Accept-Language` браузера и наконец `ADMIN_LOCALE` /
`admin.ui.default_locale` или `config('app.locale')`. Рассматриваются только
языки из `admin.ui.available_locales` (по умолчанию `['ru', 'en']`). Переводы
своих строк — в `lang/{locale}.json`.

**`/api/admin/*` отвечает 404.** Приложение привязывает свой модуль
laravel-api, не унаследованный от `AdminApiModule`, — см.
[раздел 5](#5-свой-модуль-dskripchenkolaravel-api). Или маршруты закешированы
до установки: `php artisan route:clear`.

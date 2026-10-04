---
title: Adding the admin to an existing Laravel application
audience: developer
status: stable
locale: en
---

# Adding the admin to an existing Laravel application

[Getting started](getting-started.md) takes a fresh application to a working
admin. This page is for an application that already has users, an API, a
proxy in front of it and a deploy pipeline: what the installer changes, how
to sign in your existing users, and how the admin coexists with the rest.

## 1. Prerequisites and what the installer changes

- PHP 8.2+, Laravel 11, 12 or 13.
- A database the application already migrates. The admin keeps its state in
  its own tables and uses Laravel's session (the `web` middleware group).
- Node is **not** needed unless you build the frontend yourself.

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install            # separate admin_users table (dedicated)
# or
php artisan admin:install --shared   # your existing users sign in (shared)
```

`admin:install` asks before every step that changes something; each question
has a flag (`--no-migrate`, `--no-user`, `--no-composer-hook`, `--force`), and
with `-n` it runs unattended with the defaults.

What it writes:

| What | Where |
|---|---|
| Config | `config/admin.php` |
| Migrations | `database/migrations/2026_01_01_0000*_*.php` (copies of the package's own; Laravel runs each name once) |
| Prebuilt frontend | `public/vendor/admin/` (`assets/` + `source-hash.txt`) |
| Composer hook (optional) | `"@php artisan admin:publish --ansi"` appended to `scripts.post-update-cmd` in `composer.json` |
| `--shared` only | the `auth` block of `config/admin.php` pointed at your guard, plus `database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php` |
| `--custom-build` only | `resources/js/admin.js`, an entry in the `laravel()` inputs of `vite.config.js`, `assets.vite_manifest`/`vite_entry` in `config/admin.php` |

Tables created by `migrate`: `admin_users`, `admin_password_resets`,
`admin_roles`, `admin_role_assignments`, `admin_saved_views`,
`admin_dashboard_layouts`, `admin_audit_logs`, `admin_settings`,
`admin_import_processes`. One dependency brings its own migration, which also
runs: `dskripchenko/laravel-delayed-process` (`delayed_processes`), the queue
behind async actions and imports, which the admin needs. The package's
migrations are loaded from `vendor/` even if you delete the published copies.

`dskripchenko/laravel-translatable` is optional. The admin does not require
it, so a fresh application does not get its `languages`, `translations`,
`pages`, `content_blocks` and `page_content_block` tables. Install it only if
you store model translations in the database through `TranslatableInput`
(see [i18n](concepts/i18n.md#content-translation-models)):

```bash
composer require dskripchenko/laravel-translatable
php artisan migrate
```

Upgrading from 1.47 or earlier: the package used to be pulled in
automatically. If you rely on it, require it explicitly before updating
the admin, or `composer update` removes it. If you don't, its tables stay in
the database until you drop them (see "Undoing it" below).

The installer does not touch `config/auth.php`, your models, routes or
`.env`. In the dedicated strategy the `admin` guard, the `admin_users`
provider and the `admin_users` password broker are added to the auth config
**at runtime**, and only when you have not defined guards with those names
yourself.

### Undoing it

```bash
# 1. Roll back the admin's tables (and, for --shared, the columns on users).
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-admin/database/migrations \
  --path=database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php
# The dependencies' tables, if nothing else of yours uses them
# (the second path only if laravel-translatable is installed):
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-delayed-process/databases/migrations \
  --path=vendor/dskripchenko/laravel-translatable/databases/migrations

# 2. Remove the files.
rm config/admin.php database/migrations/2026_01_01_0000*_*admin*.php
rm -r public/vendor/admin
# and the "admin:publish" line from composer.json post-update-cmd

# 3. Remove the package.
composer remove dskripchenko/laravel-admin
```

`migrate:reset --path` rolls back only the migrations found under the given
paths and skips the rest ("Migration not found"), so your own tables stay.

## 2. Who signs in: `dedicated` or `shared`

| | `dedicated` (default) | `shared` |
|---|---|---|
| Administrators live in | `admin_users`, model `Dskripchenko\LaravelAdmin\Models\AdminUser` | your table, e.g. `users`, model `App\Models\User` |
| Guard | `admin` (session), registered by the package | yours, e.g. `web` |
| Password reset | broker `admin_users`, table `admin_password_resets` | your broker, e.g. `users` |
| Who may open the admin | every active row of `admin_users` | only users with at least one admin role (or whom `canAccessAdmin()` lets in) |
| Good for | a back office whose staff are not site users | one account per person: the staff already sign in to the site |

Pick `dedicated` when in doubt: it never touches your users. Everything below
in this section is about `shared`.

### The shared strategy, step by step

This recipe was run end to end on a stock Laravel 13 application with users
already in its `users` table: API login, `system/me`, theme/locale, 2FA
enrolment and login, a refused plain user, and a browser login.

**1. Install with `--shared`:**

```bash
php artisan admin:install --shared
```

It reads your auth config — `auth.defaults.guard`, that guard's provider, the
provider's model and `auth.defaults.passwords` — and writes them into
`config/admin.php`:

```php
'auth' => [
    'strategy' => env('ADMIN_AUTH_STRATEGY', 'shared'),
    'guard' => env('ADMIN_GUARD', 'web'),
    'provider' => env('ADMIN_PROVIDER', 'users'),
    'model' => \App\Models\User::class,
    'table' => 'admin_users',        // leave it: see below
    'password_broker' => 'users',
    // ...
],
```

Doing it by hand is the same five values: `ADMIN_AUTH_STRATEGY=shared`,
`ADMIN_GUARD=web`, `ADMIN_PROVIDER=users` in `.env` (or in the config), and
`model` / `password_broker` in `config/admin.php` (they have no env keys).

Keep `auth.table` as `admin_users`: the package's own `admin_users`
migration runs in either strategy and creates the table named there, so
pointing it at `users` would make that migration fail. The table simply stays
empty in the shared strategy.

**2. Add the admin columns to your users table.** `--shared` publishes
`2026_01_01_000100_add_admin_columns_to_users_table.php` (or run
`php artisan vendor:publish --tag=admin-shared-migrations`) and `migrate`
applies it. It adds to the table of `admin.auth.model`, skipping any column
that already exists:

| Column | Used for |
|---|---|
| `locale` `string(8)`, `theme` `string(16)` | the user's choices in the panel; without them switching the theme or the language is a 500 |
| `is_active` `boolean default true` | switching an account off; `false` refuses the admin login and ends running admin sessions |
| `last_login_at`, `last_login_ip` | written at every admin login (skipped when missing) |
| `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` | the admin's TOTP 2FA |

Rolling it back drops these columns. If your table had some of them before,
delete those from the migration's `down()`.

**3. Add two traits to the model:**

```php
use Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;

class User extends Authenticatable
{
    use HasAdminAccess, HasAdminTwoFactor, HasFactory, Notifiable;
    // ...
}
```

- `HasAdminAccess` — admin roles (`roles()` through `admin_role_assignments`,
  `assignRole()`, `revokeRole()`, `hasAccess()`, `getAllPermissions()`).
- `HasAdminTwoFactor` — the encrypted casts for the 2FA columns and
  `hasTwoFactorEnabled()`. Without it a user who enables 2FA in the profile is
  still let in without a code.

**4. Make someone an administrator.** An existing user, by email:

```bash
php artisan admin:user alice@example.com --super
```

or create a new one: `php artisan admin:user "Alice" alice@example.com secret123 --super`.
In code: `$user->assignRole('super-admin')` (the role is created by the first
`admin:user --super`) or any role of your own, see
[section 6](#6-permissions-and-roles).

**5. Sign in** at `/admin/login` with the user's usual password. Over the API:

```bash
# A session cookie and the XSRF-TOKEN cookie first
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

### Who gets in

In the shared strategy a site user is **not** an administrator by default.
The login answers `403 {"errorKey":"forbidden"}` ("You do not have access to
the admin panel") to a user without an admin role, the admin API answers the
same to such a user's site session, and the shell treats them as a guest. The
user's site session is left alone.

To decide yourself — staff by email domain, a column, a gate — define
`canAccessAdmin()` on the model; its answer replaces the role check (and
applies in the dedicated strategy too):

```php
public function canAccessAdmin(string $panelId): bool
{
    return $this->is_staff;
}
```

A model can also refuse a login for reasons of its own (a suspended
company, an expired contract) with `isDisabledForLogin(): bool`; a `false`
`is_active` (or `enabled`) does the same.

### One login or two

With `ADMIN_GUARD=web` the admin and the site share one login: signing in to
the admin signs the user in to the site and logging out of the admin logs
them out of the site. To keep separate logins over the same table, declare a
guard of your own in `config/auth.php` and point the admin at it:

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

The session cookie is still the application's one; only the login state is
kept apart.

### Notes for the shared strategy

- `spatie/laravel-permission`'s `HasRoles` defines `roles()`, `assignRole()`
  and `getAllPermissions()` too, so the two traits cannot sit on one class.
  Put the admin on a subclass over the same table and name it in
  `admin.auth.model` and in the provider (a separate guard as above):
  `class AdminAccount extends User { use HasAdminAccess, HasAdminTwoFactor; }`.
  Trait methods override the inherited ones.
- Laravel Fortify keeps its 2FA in the same `two_factor_*` columns but
  encrypts them by hand. Do not add `HasAdminTwoFactor` to such a model and do
  not enable 2FA from the admin profile.
- `dskripchenko/laravel-admin-starter`'s "Users" resource edits
  `admin_users`; switch it off (`'resources' => ['users' => false]` in
  `config/admin-starter.php`) and manage your users with a resource of your
  own.
- The testing helper `ActsAsAdmin` follows the strategy: in a shared
  application it creates your own user (`admin.auth.model`), assigns it a role
  and signs it in on your guard.

## 3. Path, domain, API, sessions, proxies

| Setting | Default | Effect |
|---|---|---|
| `ADMIN_PATH` | `admin` | the SPA lives at `/{path}/*`; `''` mounts it at the root |
| `ADMIN_DOMAIN` | none | binds the SPA routes to one host, e.g. `admin.example.com` |
| `laravel-api.prefix` | `api` | the API lives at `/{prefix}/admin/{controller}/{action}` |
| `ADMIN_API_PATH` | derived | the API URL the SPA is given; leave it unset |

```dotenv
ADMIN_PATH=backoffice     # → /backoffice/login
```

**The API path.** The admin's API is a version of
`dskripchenko/laravel-api`, so it is served under laravel-api's prefix:
`/api/admin/*` by default, `/backend/admin/*` with `'prefix' => 'backend'` in
`config/laravel-api.php`. The SPA gets its API URL from the same prefix, so
moving the prefix moves both. Set `ADMIN_API_PATH` only when a proxy rewrites
the path the browser sees; it does not move the routes.

**A separate domain.** `ADMIN_DOMAIN` restricts the shell routes; the API
routes are not domain-bound and the SPA calls them on the host it was loaded
from. With `ADMIN_DOMAIN=admin.example.com` and `ADMIN_PATH=` the admin is the
root of that host and does not shadow the main site; the API prefix stays
outside the shell's catch-all.

**Sessions and cookies.** The admin uses the application's session
(`config/session.php`): the `web` middleware group is the first entry of both
`admin.middleware.shell` and `admin.middleware.api`. Whatever you set for the
site — driver, lifetime, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`,
`SESSION_SAME_SITE` — applies to the admin. If the admin is on its own
subdomain and should share the login with the site, set
`SESSION_DOMAIN=.example.com`.

**CSRF.** Every admin API call goes through `web`, CSRF check included. The
SPA sends `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie automatically. A script
calling the API with a session must do the same (see the `curl` example
above); a request without it gets `419`. Machine clients use the personal API
tokens from the profile (Sanctum, when installed) instead.

**Behind a proxy or a load balancer.** The shell hands the SPA absolute URLs
built with `url()`. Behind a TLS-terminating proxy that is not trusted they
come out as `http://…` and the browser blocks them as mixed content. Trust
the proxy in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');   // or the proxy's addresses
})
```

and set `APP_URL` to the public URL.

**Rate limits.** `ADMIN_LOGIN_THROTTLE` (`5,1` — five attempts a minute) for
the login and the 2FA steps, three password-reset emails per five minutes,
and `ADMIN_API_THROTTLE` (`240,1`) for the rest of the API.

## 4. Several panels

One panel — `admin` — covers most applications. Add a panel when a second
audience needs its own surface: a client cabinet next to the back office,
with its own users, login page, menu and resources.

Each panel has its own mount path, guard and user model, its own API version
(`/api/{id}/*`), middleware and plugins. The default panel is built from the
top-level keys of `config/admin.php`; the others are listed in
`admin.panels`:

```php
// config/admin.php
'panels' => [
    'client' => [
        'path' => 'cabinet',                         // the SPA at /cabinet
        'auth' => [
            'strategy' => 'dedicated',               // guard/provider/broker registered for you
            'guard' => 'client',
            'provider' => 'client_users',
            'model' => App\Models\ClientUser::class, // HasAdminAccess, an `enabled` or `is_active` column
            'table' => 'client_users',
            'password_broker' => 'client_users',
        ],
        'api' => App\Admin\ClientApi::class,         // the API at /api/client/*
        'plugins' => [App\Admin\ClientPanelPlugin::class],
    ],
],
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Panel\PanelApi;

final class ClientApi extends PanelApi {}   // one subclass per panel
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
        // Registered here, the resources belong to the `client` panel only.
        $admin->resources([ProjectResource::class]);
        $admin->menu()->add(MenuNode::resource('projects'));
    }
}
```

You create the `client_users` table and model yourself. A panel mounted at
the root (`'path' => ''`) must list the prefixes it should not swallow:
`'exclude_prefixes' => ['api', 'admin']`. A panel's `middleware.api` entries
are added to the shared `admin.middleware.api` stack.

## 5. Your own `dskripchenko/laravel-api` module

laravel-api resolves all API versions through one `api_module` binding. The
admin binds it to `Dskripchenko\LaravelAdmin\Http\AdminApiModule`, whose
versions are `admin` and the panels. If your application binds its own module
— the usual `ApiServiceProvider extends
Dskripchenko\LaravelApi\Providers\ApiServiceProvider` with `getApiModule()` —
yours is registered later and wins, and a module that extends laravel-api's
`BaseModule` drops the admin API: `/api/admin/*` becomes a 404.

Extend `AdminApiModule` instead and add your versions to the parent's list:

```php
namespace App\Api;

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;

class ApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return array_merge(parent::getApiVersionList(), [
            'v1' => V1Api::class,          // your BaseApi
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

Then `/api/v1/*` and `/api/admin/*` are served side by side.

- Do not override `getApiMiddleware()`. Since 1.32.0 the module's middleware
  group is version-agnostic and the stack is chosen per request: the admin
  and the panels get `admin.middleware.api` (session, CSRF, `AdminAuth`),
  your versions get only the middleware their own `BaseApi::getMethods()`
  declares. A stateless `v1` with bearer tokens therefore gets no session and
  no CSRF from the admin; if a version needs the session or `AdminLocale`, it
  declares them itself.
- `getApiPrefix()` and `getApiUriPattern()` read `laravel-api.prefix` and
  `laravel-api.uri_pattern`, shared by all versions. Changing the prefix moves
  the admin API too, and the SPA follows (see [section 3](#3-path-domain-api-sessions-proxies)).
- Do not use a version id that equals a panel id (`admin`, or a key of
  `admin.panels`).

## 6. Permissions and roles

Permissions are strings, `admin.{domain}.{action}`, with wildcards
(`admin.articles.*`, `*`). Roles (`admin_roles`) hold lists of them and are
assigned to users through `admin_role_assignments`; a user may have several.
Every resource gets `admin.{slug}.view/create/update/delete` (plus restore,
replicate and so on when enabled); screens and settings are gated the same
way. See [Permissions](concepts/permissions.md).

```bash
php artisan admin:user --super                 # interactive; Super Admin = ['*']
php artisan admin:user alice@example.com --super   # grant to an existing user
```

```php
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

$editor = Role::create([
    'name' => 'Editor',
    'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($editor);        // or ->assignRole('editor')
$user->hasAccess('admin.articles.update');   // true
$user->revokeRole('editor');
```

A UI for roles comes with the starter pack:

```bash
composer require dskripchenko/laravel-admin-starter
```

It registers itself and adds the resources "Users" (`admin_users`), "Roles"
(permissions picked from those of every registered resource and plugin) and
a read-only "Audit log". In the shared strategy switch the "Users" resource off,
see above.

### Two-factor authentication

Every user can turn on TOTP two-factor authentication in the profile. Two
settings in `config/admin.php` change that:

```php
'auth' => [
    'two_factor' => [
        // false: the profile offers no 2FA setup and enabling it is refused
        // (403 two_factor_disabled). Users who enrolled earlier still pass
        // the challenge at login and can still switch it off.
        'enabled' => true,
        // Role slugs whose holders must enable 2FA first; '*' means everyone.
        'enforce_for' => ['super-admin', 'security'],
    ],
],
```

A user covered by `enforce_for` who has not set 2FA up yet reaches only the
profile, the session and the shell: any other API request answers 403 with
`errorKey: two_factor_setup_required`, and the SPA keeps them on the profile's
"Security" section until 2FA is on.

## 7. The frontend

**Prebuilt (default).** The package ships the SPA built; `admin:install`
copies it to `public/vendor/admin` and the shell loads it. The copy must be
refreshed after every package update — that is what the composer hook does:

```json
"post-update-cmd": [
    "@php artisan admin:publish --ansi"
]
```

If you skipped the hook, add it, or run `php artisan admin:publish` in your
deploy after `composer install`. `public/vendor/admin` can be committed or
built in CI like any other public asset.

**Your own build** — for custom fields, widgets, layouts or pages written in
Vue:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

The shell then loads your Vite build (`assets.vite_manifest` /
`assets.vite_entry` in `config/admin.php`) instead of the prebuilt copy. Keep
the npm package at the same version as the composer package. See
[Frontend extension](frontend-extension.md).

## 8. Upgrading and troubleshooting

### Upgrading

```bash
composer update dskripchenko/laravel-admin
php artisan migrate                  # new migrations are loaded from vendor/
php artisan admin:publish            # done for you by the composer hook
php artisan config:clear             # if you cache the config
```

Read the [CHANGELOG](../../CHANGELOG.md) first. The package's config is merged
under yours one level deep: a new top-level key of `config/admin.php` takes
the package default, but a new key inside a block you have published (say,
inside `auth`) does not exist until you copy it over. When a release mentions
a config change, compare your file with
`vendor/dskripchenko/laravel-admin/config/admin.php`. With your own build, update the npm
packages to the same version and rebuild.

### Troubleshooting

**A blank page or "The admin frontend was not found".** The shell found
no frontend: run `php artisan admin:publish` (prebuilt) or `npm run build`
(own build; check `assets.vite_manifest` points at an existing manifest). A
404 on `/vendor/admin/assets/*` means `public/vendor/admin` did not reach the
server — a deploy that does not ship `public/vendor`, or a web server whose
document root is not `public/`.

**A bar "The admin files are out of date: run php artisan admin:publish".**
The published copy differs from the one in the installed package — the
package was updated without republishing. Run `admin:publish`, and add the
composer hook so it does not happen again.

**401 right after a successful login.** The session cookie does not come
back:
- `SESSION_SECURE_COOKIE=true` while the admin is served over plain HTTP;
- `SESSION_DOMAIN` does not match the host the admin is opened on (or the
  admin is on a subdomain and the domain is not shared);
- the `array` session driver, or a cache/session store that is not shared
  between servers;
- a proxy that is not trusted, so the request looks like HTTP (see
  [section 3](#3-path-domain-api-sessions-proxies)).
`401 {"errorKey":"session_expired"}` means the user's password was changed
since the login: sign in again.

**403 "You do not have access to the admin panel".** The shared strategy and
a user without an admin role — grant one (`admin:user email --super`).
`403 account_inactive` is a switched-off account (`is_active` or `enabled` is
false, or `isDisabledForLogin()` said so).

**419 on API calls from a script.** The request has no CSRF token: send
`X-XSRF-TOKEN` from the cookie, or use an API token.

**The wrong language.** The admin's locale is chosen from, in order: `?locale=`,
the SPA's `X-Admin-Locale` header, the user's `locale` column, the
`admin_locale` cookie, the browser's `Accept-Language`, and finally
`ADMIN_LOCALE` / `admin.ui.default_locale` or `config('app.locale')`. Only
the languages in `admin.ui.available_locales` (`['ru', 'en']` by default) are
considered. Translations of your own strings go to `lang/{locale}.json`.

**`/api/admin/*` is a 404.** Your application binds its own laravel-api module
that does not extend `AdminApiModule` — see [section 5](#5-your-own-dskripchenkolaravel-api-module).
Or the routes are cached from before the installation: `php artisan route:clear`.

---
title: Demo mode
audience: developer
status: stable
locale: en
---

# Demo mode

Demo mode turns an installation into a public demonstration stand: visitors
sign in with one click as one of the demo accounts, and the operations that
would let one visitor spoil the stand for the next are refused. It is off by
default.

```dotenv
ADMIN_DEMO=true
```

## Demo accounts

List the accounts in `config/admin.php`; the login page shows a
"Sign in as …" button for each of them:

```php
'demo' => [
    'enabled' => (bool) env('ADMIN_DEMO', false),
    'accounts' => [
        [
            'label' => 'Administrator',
            'email' => 'admin@demo.test',
            'password' => 'demo',
            'description' => 'Full access',
        ],
        [
            'label' => 'Editor',
            'email' => 'editor@demo.test',
            'password' => 'demo',
            'description' => 'Content only, no settings',
        ],
    ],
],
```

A click fills in the form and signs in through the ordinary login endpoint,
so everything that applies to a login — the throttle, a disabled account,
2FA — applies here too. `label` and `description` go through the
translator, so they may be translation keys.

> **Warning** The passwords are sent to every visitor of the login page.
> List demo accounts only, never a real one, and create them with the
> permissions you are happy to show.

The accounts themselves are ordinary users: create them in a seeder.

## Read-only guard

With `readonly` on (the default once demo mode is enabled) the API refuses a
set of operations with `403` and `errorKey: demo_readonly`. The panel shows
the refusal as a toast — "Demo mode: this action is disabled." — instead of
a generic error.

```php
'demo' => [
    'readonly' => (bool) env('ADMIN_DEMO_READONLY', true),

    // API actions as `controller.action` patterns; `*` matches anything.
    'blocked' => [
        'profile.update',
        'profile.changePassword',
        'profile.twoFactorEnable',
        'profile.twoFactorConfirm',
        'profile.twoFactorDisable',
        'profile.twoFactorRegenerateCodes',
        'profile.tokenCreate',
        'profile.tokenRevoke',
        'auth.startImpersonation',
        'import.*',
        'settings_*.update',
    ],

    // Writes to the resources of these models are refused. null: the
    // panel's user model and Role — nobody can lock the next visitor out.
    'protected_models' => null,
    'protected_actions' => [
        'create', 'update', 'inlineUpdate', 'replicate', 'reorder',
        'delete', 'restore', 'forceDelete', 'action',
    ],

    // Uploaded files over this size are refused; 0 switches the limit off.
    'max_upload_kb' => 2048,
],
```

What the defaults cover:

| Operation | Why |
|---|---|
| Changing the profile and the password | The next visitor could not sign in |
| Enabling or disabling 2FA, regenerating recovery codes | Same |
| Creating and revoking API tokens | A token outlives the demo session |
| Impersonation | Opens every account of the stand |
| Writes to users and roles | Deleting the demo accounts or stripping their permissions |
| Settings | Settings are shared by every visitor |
| Import | Bulk writes |
| Large uploads | Disk space |

Everything else — creating, editing and deleting your demo records — stays
open; that is what a visitor comes to try.

### Tuning the lists

The lists are plain config, so a host adds or removes entries:

```php
// Allow the profile, refuse the "articles" bulk actions too.
'blocked' => [
    'profile.changePassword',
    'articles.action',
    'settings_*.update',
],

// Protect one more model.
'protected_models' => [
    App\Models\User::class,
    Dskripchenko\LaravelAdmin\Permission\Models\Role::class,
    App\Models\Tenant::class,
],
```

The controller of a resource is its slug (`articles.update`), of a settings
page `settings_{slug}`, of a screen its slug (`reports.runMethod`). Built-in
controllers: `auth`, `profile`, `system`, `dashboard`, `audit`, `import`,
`uploads`, `notifications`, `delayed`.

`readonly` is checked per request, so it can also be switched at runtime — in
a middleware, for a particular user:

```php
config(['admin.demo.readonly' => ! $request->user()?->is_staff]);
```

## The banner

Tell visitors what kind of stand they are on with the installation banner,
`admin.notice`. It is drawn by the shell, above the panel and on the login
page:

```dotenv
ADMIN_NOTICE="Demo stand: the data resets every hour"
ADMIN_NOTICE_HREF=https://example.com/docs
ADMIN_NOTICE_COUNTDOWN_LABEL="until the reset"
```

`countdown_to` (ISO-8601) adds a countdown; set it from a middleware, since a
cached config would freeze a computed value:

```php
config(['admin.notice.countdown_to' => now()->startOfHour()->addHour()->toIso8601String()]);
```

## Resetting the data

The package does not reset the stand itself: schedule your own command that
restores the database (`migrate:fresh --seed`, a dump, a snapshot) and
re-creates the demo accounts.

```php
// routes/console.php
Schedule::command('migrate:fresh --seed --force')->hourly();
```

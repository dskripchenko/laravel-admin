---
title: Getting Started
audience: developer
status: stable
locale: en
---

# Getting Started

This walks you from a fresh Laravel app to a working admin with a custom
resource in about ten minutes.

## Prerequisites

- PHP 8.2+
- Laravel 11, 12 or 13
- Node — only if you build the frontend yourself (`--custom-build`)
- An Eloquent model you'd like to manage (we'll use `Article`)

## Install

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` publishes `config/admin.php` and the migrations, publishes
the prebuilt frontend to `public/vendor/admin`, runs `migrate` and creates
the first administrator. It creates `admin_users`, `admin_roles`,
`admin_settings`, `admin_audit_logs`, `admin_dashboard_layouts` and a few
more tables, plus `delayed_processes` from
`dskripchenko/laravel-delayed-process`, the queue behind async actions. That
is the only table a dependency adds; the full list is in
[integration](integration.md#1-prerequisites-and-what-the-installer-changes).

Adding the admin to an application that already has users? With
`php artisan admin:install --shared` they sign in with their usual accounts
instead of a separate `admin_users` table — see
[Adding the admin to an existing application](integration.md).

No Node and no build step are needed: the package ships the admin SPA
already built. `admin:install` also offers to add
`php artisan admin:publish` to composer's `post-update-cmd`, so the
frontend is republished whenever the package is updated (the admin shows a
warning when the published copy is out of date).

Options: `--no-migrate`, `--no-user`, `--no-composer-hook`, `--force`
(overwrite published files), `--custom-build` (see below).

### Custom fields, widgets or pages: your own build

To register your own Vue components, build the admin with your
application's Vite instead of using the prebuilt bundle:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

`--custom-build` creates `resources/js/admin.js`, adds it to the inputs of
`laravel-vite-plugin` in `vite.config.js` and points `config('admin.assets')`
at the Vite manifest. Register your components in that entry before
`createAdminApp()` — see [Frontend extension](frontend-extension.md).

## Create the first admin user

If you skipped it during `admin:install`, or for more administrators:

```bash
php artisan admin:user --super
```

It asks for the name, email and password (or takes them as arguments:
`admin:user "Admin" admin@example.com secret123 --super`). `--super` grants
the role with every permission.

Visit `/admin/login` with these credentials. The path comes from
`ADMIN_PATH` (default `admin`).

## Your first Resource

Generate a skeleton with the interactive wizard — it asks for the labels,
the model (or a table), the form and table columns, the permission and the
icon:

```bash
php artisan admin:make-resource
```

Or write it by hand:

```php
namespace App\Admin\Resources;

use App\Models\Article;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;
    public static string $icon  = 'file-text';

    public static function label(): string { return 'Articles'; }

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Input::make('slug')->required(),
            Textarea::make('excerpt')->rows(3),
            Select::make('status')->options([
                'draft' => 'Draft',
                'review' => 'In review',
                'published' => 'Published',
                'archived' => 'Archived',
            ])->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('status')->asBadge(),
            TableColumn::make('created_at')->asDateTime()->sort(),
        ];
    }
}
```

Register in your `AppServiceProvider::boot()`:

```php
use Dskripchenko\LaravelAdmin\Facades\Admin;
use App\Admin\Resources\ArticleResource;

public function boot(): void
{
    Admin::resources([ArticleResource::class]);
}
```

That's it. List/create/edit/view screens are generated automatically:

| URL | What |
|---|---|
| `/admin/r/articles` | List + filters + pagination |
| `/admin/r/articles/create` | Create form |
| `/admin/r/articles/{id}/edit` | Edit form |
| `/admin/r/articles/{id}` | Read-only infolist |

## Next steps

- [Adding the admin to an existing application](integration.md) — your
  existing users, path and domain, proxies, several panels, your own
  laravel-api module, upgrading and troubleshooting.
- [Hierarchical menu](concepts/menu.md) — replace auto-fill with explicit
  navigation tree.
- [Custom Screens](concepts/screens.md) — non-CRUD pages (forms, reports).
- [Permissions](concepts/permissions.md) — gate per-action access.
- [Fields reference](fields-reference.md) — full field catalog.
- [Layouts reference](layouts-reference.md) — Tabs/Wizard/Modal/Drawer.
- [Demo mode](demo-mode.md) — a public demonstration stand: one-click demo
  accounts and a read-only guard.

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
- Node 20+ to build the frontend bundle
- An Eloquent model you'd like to manage (we'll use `Article`)

## Install

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` publishes `config/admin.php` and the migrations, runs
`migrate` and offers to create the first administrator. It creates
`admin_users`, `admin_roles`, `admin_settings`, `audit_logs`,
`dashboard_layouts` and a few more tables. Options: `--no-migrate`,
`--no-user`, `--force` (overwrite published files).

## Frontend bundle

The admin is a Vue SPA. Install it with the UI kit and the default WYSIWYG
editor:

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
```

Create the entry `resources/js/admin.js`:

```js
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'
import '@dskripchenko/wysiwyg/style.css'

import { createAdminApp } from '@dskripchenko/laravel-admin'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

Add it to the inputs of `laravel-vite-plugin` in `vite.config.js`:

```js
laravel({
    input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'],
    refresh: true,
}),
```

and point the admin shell at the Vite manifest in `config/admin.php`:

```php
'assets' => [
    'vite_manifest' => public_path('build/manifest.json'),
    'vite_entry' => 'resources/js/admin.js',
    'vite_base_url' => '/build/',
    'css' => [],
    'js' => [],
],
```

Build:

```bash
npm run build
```

Without the `assets` settings the shell has nothing to load and the admin
page stays blank.

## Create the first admin user

If you skipped it during `admin:install`:

```bash
php artisan admin:user --super
```

It asks for the name, email and password (or takes them as arguments:
`admin:user "Admin" admin@example.com secret123 --super`). `--super` grants
the role with every permission.

Visit `/admin/login` with these credentials. The path comes from
`ADMIN_PATH` (default `admin`).

## Your first Resource

Generate a skeleton:

```bash
php artisan admin:make-resource ArticleResource
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

- [Hierarchical menu](concepts/menu.md) — replace auto-fill with explicit
  navigation tree.
- [Custom Screens](concepts/screens.md) — non-CRUD pages (forms, reports).
- [Permissions](concepts/permissions.md) — gate per-action access.
- [Fields reference](fields-reference.md) — full field catalog.
- [Layouts reference](layouts-reference.md) — Tabs/Wizard/Modal/Drawer.

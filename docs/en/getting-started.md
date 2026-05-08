---
title: Getting Started
audience: developer
status: stable
locale: en
---

# Getting Started

This walks you from a fresh Laravel 12 app to a working admin with a
custom resource in ~5 minutes.

## Prerequisites

- PHP 8.5+
- Laravel 12.x
- Node 20+ for the frontend bundle
- A clean Eloquent model you'd like to manage (we'll use `Article`)

## Install

```bash
composer require dskripchenko/laravel-admin
php artisan vendor:publish --tag=admin-config
php artisan migrate
```

This creates `admin_users`, `admin_roles`, `admin_settings`,
`audit_logs`, `dashboard_layouts` and a few more tables.

## Frontend bundle

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui
```

Add an entry in `resources/js/admin.js`:

```js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

Build:

```bash
npm run build
```

## Create the first admin user

```bash
php artisan admin:make-user
```

Or via tinker:

```php
\Dskripchenko\LaravelAdmin\Models\AdminUser::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => 'password',
])->assignRole(
    \Dskripchenko\LaravelAdmin\Permission\Models\Role::firstOrCreate(
        ['slug' => 'super'],
        ['name' => 'Super', 'permissions' => ['*']],
    ),
);
```

Visit `/admin/login` with these credentials.

## Your first Resource

Generate skeleton:

```bash
php artisan admin:make-resource ArticleResource
```

Or write by hand:

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
            TableColumn::make('id')->sortable(),
            TableColumn::make('title')->sortable()->searchable(),
            TableColumn::make('status')->preset('badge'),
            TableColumn::make('created_at')->preset('datetime')->sortable(),
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
| `/admin/r/articles/{id}/view` | Read-only infolist |

## Next steps

- [Hierarchical menu](concepts/menu.md) — replace auto-fill with explicit
  navigation tree.
- [Custom Screens](concepts/screens.md) — non-CRUD pages (forms, reports).
- [Permissions](concepts/permissions.md) — gate per-action access.
- [Fields reference](fields-reference.md) — full field catalog.
- [Layouts reference](layouts-reference.md) — Tabs/Wizard/Modal/Drawer.

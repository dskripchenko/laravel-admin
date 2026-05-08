---
title: Быстрый старт
audience: developer
status: stable
locale: ru
translated_from: en/getting-started.md
translated_at: 2026-05-08
---

# Быстрый старт

Этот документ проведёт от чистого Laravel 12 приложения до работающей
админки с кастомным resource'ом за ~5 минут.

## Требования

- PHP 8.5+
- Laravel 12.x
- Node 20+ для frontend-bundle
- Чистая Eloquent-модель, которой хочешь управлять (для примера —
  `Article`)

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan vendor:publish --tag=admin-config
php artisan migrate
```

Это создаст таблицы `admin_users`, `admin_roles`, `admin_settings`,
`audit_logs`, `dashboard_layouts` и несколько других.

## Frontend bundle

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui
```

Точка входа в `resources/js/admin.js`:

```js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

Сборка:

```bash
npm run build
```

## Первый admin-пользователь

```bash
php artisan admin:make-user
```

Или через tinker:

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

Открой `/admin/login` с этими credentials.

## Первый Resource

Сгенерировать скелет:

```bash
php artisan admin:make-resource ArticleResource
```

Или вручную:

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

    public static function label(): string { return 'Статьи'; }

    public function fields(): array
    {
        return [
            Input::make('title')->required()->title('Заголовок'),
            Input::make('slug')->required(),
            Textarea::make('excerpt')->rows(3)->title('Аннотация'),
            Select::make('status')->options([
                'draft' => 'Черновик',
                'review' => 'На ревью',
                'published' => 'Опубликовано',
                'archived' => 'В архиве',
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

Регистрация в `AppServiceProvider::boot()`:

```php
use Dskripchenko\LaravelAdmin\Facades\Admin;
use App\Admin\Resources\ArticleResource;

public function boot(): void
{
    Admin::resources([ArticleResource::class]);
}
```

Готово. Страницы list/create/edit/view генерируются автоматически:

| URL | Что |
|---|---|
| `/admin/r/articles` | Список + фильтры + пагинация |
| `/admin/r/articles/create` | Форма создания |
| `/admin/r/articles/{id}/edit` | Форма редактирования |
| `/admin/r/articles/{id}/view` | Read-only infolist |

## Дальше

- [Иерархическое меню](concepts/menu.md) — заменить auto-fill явным
  деревом навигации.
- [Custom Screens](concepts/screens.md) — non-CRUD страницы (формы,
  отчёты).
- [Permissions](../en/concepts/permissions.md) (en) — гранулярный
  контроль доступа.
- [Каталог полей](../en/fields-reference.md) (en) — все типы полей.
- [Каталог layout'ов](../en/layouts-reference.md) (en) — Tabs/Wizard/
  Modal/Drawer.

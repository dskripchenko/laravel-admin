---
title: Быстрый старт
audience: developer
status: stable
locale: ru
translated_from: en/getting-started.md
translated_at: 2026-10-01
---

# Быстрый старт

Этот документ проведёт от чистого Laravel-приложения до работающей
админки с собственным ресурсом примерно за десять минут.

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13
- Node 20+ для сборки фронтенда
- Eloquent-модель, которой нужно управлять (для примера — `Article`)

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` публикует `config/admin.php` и миграции, запускает
`migrate` и предлагает создать первого администратора. Появятся таблицы
`admin_users`, `admin_roles`, `admin_settings`, `audit_logs`,
`dashboard_layouts` и несколько других. Опции: `--no-migrate`, `--no-user`,
`--force` (перезаписать опубликованные файлы).

## Фронтенд

Админка — Vue SPA. Ставится вместе с UI-китом и редактором WYSIWYG по
умолчанию:

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
```

Точка входа `resources/js/admin.js`:

```js
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'
import '@dskripchenko/wysiwyg/style.css'

import { createAdminApp } from '@dskripchenko/laravel-admin'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

Добавьте её во входы `laravel-vite-plugin` в `vite.config.js`:

```js
laravel({
    input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'],
    refresh: true,
}),
```

и укажите оболочке админки Vite-манифест в `config/admin.php`:

```php
'assets' => [
    'vite_manifest' => public_path('build/manifest.json'),
    'vite_entry' => 'resources/js/admin.js',
    'vite_base_url' => '/build/',
    'css' => [],
    'js' => [],
],
```

Сборка:

```bash
npm run build
```

Без настроек `assets` оболочке нечего загрузить, и страница админки
останется пустой.

## Первый администратор

Если пропустили этот шаг в `admin:install`:

```bash
php artisan admin:user --super
```

Команда спросит имя, email и пароль (или возьмёт их аргументами:
`admin:user "Admin" admin@example.com secret123 --super`). `--super`
назначает роль со всеми правами.

Откройте `/admin/login` с этими данными. Путь задаётся `ADMIN_PATH`
(по умолчанию `admin`).

## Первый ресурс

Сгенерировать заготовку:

```bash
php artisan admin:make-resource ArticleResource
```

Или написать вручную:

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
            Input::make('title')->required(),
            Input::make('slug')->required(),
            Textarea::make('excerpt')->rows(3),
            Select::make('status')->options([
                'draft' => 'Черновик',
                'review' => 'На проверке',
                'published' => 'Опубликована',
                'archived' => 'В архиве',
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
| `/admin/r/articles/{id}` | Read-only infolist |

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

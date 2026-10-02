---
title: Быстрый старт
audience: developer
status: stable
locale: ru
translated_from: en/getting-started.md
translated_at: 2026-10-02
---

# Быстрый старт

Этот документ проведёт от чистого Laravel-приложения до работающей
админки с собственным ресурсом примерно за десять минут.

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13
- Node — только если собираете фронтенд сами (`--custom-build`)
- Eloquent-модель, которой нужно управлять (для примера — `Article`)

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` публикует `config/admin.php` и миграции, публикует готовую
сборку фронтенда в `public/vendor/admin`, запускает `migrate` и создаёт
первого администратора. Появятся таблицы `admin_users`, `admin_roles`,
`admin_settings`, `admin_audit_logs`, `admin_dashboard_layouts` и несколько
других.

Подключаете админку к приложению, где уже есть пользователи? С
`php artisan admin:install --shared` они входят своими обычными учётками
вместо отдельной таблицы `admin_users` — см.
[подключение к существующему приложению](integration.md).

Node и сборка не нужны: пакет поставляет SPA админки уже собранной.
`admin:install` также предлагает добавить `php artisan admin:publish` в
`post-update-cmd` composer — тогда фронтенд публикуется заново при каждом
обновлении пакета (если опубликованная копия устарела, админка покажет
предупреждение).

Опции: `--no-migrate`, `--no-user`, `--no-composer-hook`, `--force`
(перезаписать опубликованные файлы), `--custom-build` (см. ниже).

### Свои поля, виджеты и страницы: собственная сборка

Чтобы регистрировать свои Vue-компоненты, соберите админку Vite'ом
приложения вместо готовой сборки:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

`--custom-build` создаёт `resources/js/admin.js`, добавляет его во входы
`laravel-vite-plugin` в `vite.config.js` и направляет `config('admin.assets')`
на Vite-манифест. Компоненты регистрируются в этой точке входа до
`createAdminApp()` — см. [расширение фронтенда](frontend-extension.md).

## Первый администратор

Если пропустили этот шаг в `admin:install` или нужны ещё администраторы:

```bash
php artisan admin:user --super
```

Команда спросит имя, email и пароль (или возьмёт их аргументами:
`admin:user "Admin" admin@example.com secret123 --super`). `--super`
назначает роль со всеми правами.

Откройте `/admin/login` с этими данными. Путь задаётся `ADMIN_PATH`
(по умолчанию `admin`).

## Первый ресурс

Сгенерировать заготовку интерактивным мастером — он спросит названия,
модель (или таблицу), колонки формы и таблицы, право и иконку:

```bash
php artisan admin:make-resource
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

- [Подключение к существующему приложению](integration.md) — ваши
  пользователи, путь и домен, прокси, несколько панелей, свой модуль
  laravel-api, обновление и типичные проблемы.
- [Иерархическое меню](concepts/menu.md) — заменить auto-fill явным
  деревом навигации.
- [Custom Screens](concepts/screens.md) — non-CRUD страницы (формы,
  отчёты).
- [Permissions](concepts/permissions.md) — гранулярный
  контроль доступа.
- [Каталог полей](fields-reference.md) — все типы полей.
- [Каталог layout'ов](layouts-reference.md) — Tabs/Wizard/
  Modal/Drawer.
- [Демо-режим](demo-mode.md) — публичный демо-стенд: вход под демо-аккаунтом
  в один клик и защита от записи.

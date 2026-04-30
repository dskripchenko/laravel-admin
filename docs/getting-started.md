# Getting Started

`dskripchenko/laravel-admin` — Laravel-конструктор админки. Декларативно описываете
Resource'ы и Screen'ы — пакет компилирует их в JSON-API + SPA-shell.

## Требования

- PHP 8.5+
- Laravel 12+
- Composer

## Установка

```bash
composer require dskripchenko/laravel-admin
php artisan vendor:publish --tag=admin-config       # публикует config/admin.php
php artisan vendor:publish --tag=admin-migrations   # копирует миграции (опционально)
php artisan migrate
php artisan admin:user                              # создаёт суперюзера
```

После этого SPA доступен на `/admin/*`, JSON-API — на `/api/admin/*`.
Префиксы можно изменить в `config/admin.php` через `path` и `api_path`.

## Первый Resource: Article

Задача: создать страницу управления статьями со списком, фильтрами, формой
создания/редактирования.

### 1. Eloquent-модель

```php
// app/Models/Article.php
class Article extends Model
{
    use \Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable; // опционально

    protected $fillable = ['title', 'slug', 'body', 'is_published'];

    protected $casts = ['is_published' => 'boolean'];
}
```

### 2. Resource-класс

```php
// app/Admin/Resources/ArticleResource.php
namespace App\Admin\Resources;

use App\Models\Article;
use Dskripchenko\LaravelAdmin\Field\{Input, Wysiwyg, Switcher, Slug};
use Dskripchenko\LaravelAdmin\Filter\{InputFilter, SwitcherFilter};
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;

    public function fields(): array
    {
        return [
            Input::make('title')->required()->title('Заголовок'),
            Slug::make('slug')->from('title'),
            Wysiwyg::make('body')->preset('default'),
            Switcher::make('is_published')->title('Опубликована'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('is_published')->asBoolean('Да', 'Нет'),
            TableColumn::make('created_at')->asDateTime()->sort(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('title')->label('Поиск по заголовку'),
            SwitcherFilter::for('is_published')->label('Опубликована'),
        ];
    }
}
```

### 3. Регистрация

В `AppServiceProvider::boot()`:

```php
use Dskripchenko\LaravelAdmin\Admin;

public function boot(Admin $admin): void
{
    $admin->resources([
        \App\Admin\Resources\ArticleResource::class,
    ]);
}
```

Готово. SPA автоматически:

- Добавит `Articles` в боковое меню.
- Сгенерирует list-страницу с table + filters + кнопкой «Создать».
- Сгенерирует form-страницы create/edit/view.
- Создаст endpoint'ы `/api/admin/articles/{meta,search,read,create,update,delete,...}`.
- Применит RBAC: каждый endpoint требует permission'а вида `admin.articles.{view|create|update|delete}`.

## Базовые концепты

### Resource

Описание модели в админке: какие поля показывать, какие колонки в таблице,
какие фильтры доступны, какие custom-actions. См. [API: Resources](api/resources.md)
и [API: Registration](api/registration.md).

### Field

Декларация одного поля формы: `Input`, `Number`, `Select`, `Wysiwyg` и т.д.
30+ типов из коробки. См. [API: Schemas](api/schemas.md).

### Layout

Структурный элемент: `Rows`, `Columns`, `Tabs`, `Modal`, `Wizard`,
`Infolist`, `Dashboard` и др. См. [API: Screens](api/screens.md).

### Action

Кнопка/ссылка: `Button`, `Link`, `BulkAction`, `ModalAction`, `DropDown`,
`AsyncAction`. Каждая может быть привязана к row, bulk, command-bar.

### Screen

Произвольная страница со state + layout. Для CRUD не нужен —
GeneratedListScreen/CreateScreen/EditScreen/ViewScreen генерируются
автоматически из Resource. Custom-страницы (dashboards, reports) —
наследуют от `Screen`.

### Permission / Role

RBAC встроен. `admin.{slug}.{action}` permission'ы создаются автоматически
для каждого зарегистрированного Resource'а. Suport wildcard'ов: `*` или
`admin.users.*`.

```php
$role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'permissions' => [
    'admin.articles.*',
    'admin.categories.view',
]]);
$user->assignRole($role);
```

См. [API: Auth](api/auth.md).

## Что дальше

- [Recipes](recipes/) — типовые сценарии (custom-actions, file-upload, multi-tenancy и т.п.)
- [API Reference](api/) — полное описание endpoint'ов
- [Architecture](ARCHITECTURE.md) — design-документ
- [Sister-packs](sister-packs/) — дополнительные пакеты (starter, search, media и др.)

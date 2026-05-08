---
title: Resources
audience: developer
status: stable
locale: ru
translated_from: en/concepts/resources.md
translated_at: 2026-05-08
---

# Resources

**Resource** связывает Eloquent-модель с админкой: form, table, filters,
actions, permissions.

## Минимальный Resource

```php
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = \App\Models\Article::class;
    public static string $icon  = 'file-text';

    public function fields(): array
    {
        return [Input::make('title')->required()];
    }

    public function columns(): array
    {
        return [TableColumn::make('id'), TableColumn::make('title')];
    }
}
```

Регистрация: `Admin::resources([ArticleResource::class])`.

## Slug, label, group, icon

```php
public static string $icon = 'package';        // имя lucide-иконки
public static ?string $group = 'Каталог';      // секция в sidebar

public static function slug(): string { return 'articles'; }    // URL = /admin/r/articles
public static function label(): string { return 'Статьи'; }
```

## Fields

```php
Input::make('email')->type('email')->required(),
Number::make('price')->min(0)->step(0.01),
Select::make('status')->options(['draft' => 'Черновик', 'published' => 'Опубликовано'])->required(),
DatePicker::make('published_at')->withTime(),
RelationSelect::make('category_id')->relation('category')->display('name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

См. [каталог полей](../en/fields-reference.md) (en) для всех типов.

### Видимость по режимам

```php
Input::make('slug')->required()->visibleOn(['create', 'update'])
                              ->hiddenOn(['view']),
```

### Reactive fields

```php
Input::make('slug')->reactive(['title' => 'slugify']),
```

## Columns (таблица)

```php
TableColumn::make('id')->sortable(),
TableColumn::make('title')->sortable()->searchable(),
TableColumn::make('status')->preset('badge')->align('center'),
TableColumn::make('created_at')->preset('datetime')->sortable(),
TableColumn::make('price')->preset('money')->align('right'),
TableColumn::make('actions')->view(),  // built-in row actions column
```

Presets: `badge`, `datetime`, `date`, `money`, `boolean`, `bytes`,
`relative-time`, `code`, `truncate`.

## Filters

```php
public function filters(): array
{
    return [
        BaseInputFilter::make('search')->searchableFields(['title', 'slug']),
        BaseSelectFromOptionsFilter::make('status')->options([...]),
        BaseDateFilter::make('created_at')->range(),
        BaseSelectFromModelFilter::make('category_id')->model(Category::class),
        TrashedFilter::make(),
    ];
}
```

## Actions

```php
public function actions(): array
{
    return [
        Button::make('Опубликовать')->method('publish')->position(['row']),
        BulkAction::make('В архив')->method('archive')->confirm('Архивировать N статей?'),
    ];
}

public function publish(int $id): void
{
    \App\Models\Article::find($id)->update(['status' => 'published']);
}
```

## Soft-delete / Restore / Force-delete

Если модель использует `SoftDeletes`, admin авто-включает:
- `TrashedFilter`
- `Restore` row action
- `ForceDelete` row action (gated `admin.{slug}.force-delete`)

## Replicate / Reorder

```php
public static function replicable(): bool { return true; }
public static string $reorderColumn = 'position';
```

## Permissions

Default base: `admin.{slug}`. Auto-derived: `view`, `create`, `update`,
`delete`, `restore`, `force-delete`, `replicate`, `reorder`. Override:

```php
public static function permission(): string
{
    return 'admin.articles';
}
```

## Связи в полях/колонках

```php
TableColumn::make('author.name')->label('Автор'),  // dot-notation auto-eager
RelationSelect::make('author_id')->relation('author')->display('name')->searchable(),
```

## См. также

- [Screens](screens.md)
- [Permissions](../en/concepts/permissions.md) (en)
- [Каталог полей](../en/fields-reference.md) (en)

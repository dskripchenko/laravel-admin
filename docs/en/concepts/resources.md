---
title: Resources
audience: developer
status: stable
locale: en
---

# Resources

A **Resource** wires an Eloquent model into the admin: form, table,
filters, actions, permissions.

## Minimal Resource

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

Register: `Admin::resources([ArticleResource::class])`.

## Slug, label, group, icon

```php
public static string $icon = 'package';        // lucide name
public static ?string $group = 'Catalog';      // sidebar section

public static function slug(): string { return 'articles'; }    // URL = /admin/r/articles
public static function label(): string { return 'Articles'; }   // sidebar label
```

## Fields

Each field returns a descriptor. Common patterns:

```php
Input::make('email')->type('email')->required()->placeholder('user@host'),
Number::make('price')->min(0)->step(0.01),
Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->required(),
DatePicker::make('published_at')->withTime(),
RelationSelect::make('category_id')->relation('category')->display('name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

See [fields reference](../fields-reference.md) for the full catalog.

### Visibility per mode

```php
Input::make('slug')->required()->visibleOn(['create', 'update'])
                              ->hiddenOn(['view']),
```

### Reactive fields

```php
Input::make('slug')->reactive(['title' => 'slugify']),
```

The frontend recomputes `slug` whenever `title` changes (field strategy
registered via `registerField`).

## Columns (table)

```php
TableColumn::make('id')->sortable(),
TableColumn::make('title')->sortable()->searchable(),
TableColumn::make('status')->preset('badge')->align('center'),
TableColumn::make('created_at')->preset('datetime')->sortable(),
TableColumn::make('price')->preset('money')->align('right'),
TableColumn::make('actions')->view(),  // built-in row actions column
```

Available presets: `badge`, `datetime`, `date`, `money`, `boolean`,
`bytes`, `relative-time`, `code`, `truncate`.

## Filters

```php
public function filters(): array
{
    return [
        BaseInputFilter::make('search')->searchableFields(['title', 'slug']),
        BaseSelectFromOptionsFilter::make('status')->options([...]),
        BaseDateFilter::make('created_at')->range(),
        BaseSelectFromModelFilter::make('category_id')->model(Category::class),
        TrashedFilter::make(),  // soft-delete
    ];
}
```

## Actions

Row actions / bulk / command-bar — see [Actions](actions.md).

```php
public function actions(): array
{
    return [
        Button::make('Publish')->method('publish')->position(['row']),
        BulkAction::make('Archive')->method('archive')->confirm('Archive N articles?'),
    ];
}

public function publish(int $id): void
{
    \App\Models\Article::find($id)->update(['status' => 'published']);
}
```

## Soft-delete / Restore / Force-delete

If your model uses `SoftDeletes`, the admin auto-enables:
- `TrashedFilter` (active/trashed/with)
- `Restore` row action
- `ForceDelete` row action (gated by `admin.{slug}.force-delete`)

## Replicate

```php
public static function replicable(): bool { return true; }

public function onReplicate(Model $copy, Model $source): void
{
    $copy->title = $source->title.' (copy)';
}
```

## Reorder

For models with a `position` column:

```php
public static string $reorderColumn = 'position';
```

The list-screen gets a drag-handle column.

## Permissions

Default base permission: `admin.{slug}`. Auto-derived sub-permissions:
`view`, `create`, `update`, `delete`, `restore`, `force-delete`,
`replicate`, `reorder`. Override:

```php
public static function permission(): string
{
    return 'admin.articles';     // admin.articles.view, admin.articles.update, ...
}
```

## Searchable / Sortable

```php
public function searchableFields(): array { return ['title', 'slug']; }
public function defaultSort(): array { return ['created_at' => 'desc']; }
```

## Relationships in fields/columns

```php
TableColumn::make('author.name')->label('Author'),  // dot-notation auto-eager-loads
RelationSelect::make('author_id')
    ->relation('author')
    ->display('name')
    ->searchable(),
```

## See also

- [Screens](screens.md) — non-CRUD pages
- [Permissions](permissions.md) — RBAC details
- [Fields reference](../fields-reference.md)
- [Layouts reference](../layouts-reference.md)

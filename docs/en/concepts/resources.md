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
RelationSelect::make('category_id')->relation(Category::class, 'name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

See [fields reference](../fields-reference.md) for the full catalog.

### Visibility per mode

```php
Input::make('slug')->required()->onView(false),     // create + update only
Password::make('password')->onUpdate(false),         // create only
```

Each of `onCreate()`, `onUpdate()`, `onView()` takes a bool and defaults to
`true`; a field left alone is shown in all three. To show a field only while
another one holds a value, use `visibleWhen('driver', 's3')`.

### Fields derived from other fields

```php
Slug::make('slug')->from('title'),
```

The frontend regenerates `slug` whenever `title` changes, until the slug is
edited by hand. For anything more involved — options that depend on another
field, a computed total — wrap the fields in a
[Listener](../layouts-reference.md#listener-reactive-part-of-a-form).

## Columns (table)

```php
TableColumn::make('id')->sort(),
TableColumn::make('title')->sort()->search(),
TableColumn::make('status')->asBadge(['published' => 'success'])->align('center'),
TableColumn::make('created_at')->asDateTime()->sort(),
TableColumn::make('price')->asMoney('USD')->align('right'),
TableColumn::make('cover')->asImage(),
```

Formatters: `asDate()`, `asDateTime()`, `asMoney()`, `asBoolean()`,
`asBytes()`, `asBadge()`, `asLink()`, `asImage()`, or `format(callable)` for
anything else. Row actions are rendered by the table itself.

## Filters

```php
public function filters(): array
{
    return [
        InputFilter::for('title')->label('Title'),               // LIKE %…%
        OptionsFilter::for('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        DateRangeFilter::for('created_at'),                       // {from, to}
        SelectFromModelFilter::for('category_id')->fromModel(Category::class, 'name'),
        SwitcherFilter::for('is_featured'),
    ];
}
```

Filters live in `Dskripchenko\LaravelAdmin\Filter`. For a model with
`SoftDeletes` a `TrashedFilter` is added automatically. `QueryFilter` takes
a closure for anything the others do not cover.

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

public function publish(array $ids, array $payload = []): void
{
    \App\Models\Article::whereKey($ids)->update(['status' => 'published']);
}
```

The action is dispatched by its name, and the resource method receives the
selected ids and the action's payload. The name is derived from the label's
Latin letters and digits only — a label in another script collapses to
`action`, and several such actions become one — so with non-Latin labels set
the name explicitly with `withName('publish')`.

## Soft-delete / Restore / Force-delete

If your model uses `SoftDeletes`, the admin auto-enables:
- `TrashedFilter` (active/trashed/with)
- `Restore` row action
- `ForceDelete` row action (gated by `admin.{slug}.force-delete`)

## Replicate

```php
public function replicable(): bool { return true; }

public function replicate(Model $original): Model
{
    $copy = parent::replicate($original);
    $copy->slug = $original->slug.'-copy';

    return $copy;
}
```

By default `replicate()` is Eloquent's `Model::replicate()` plus a copy
suffix on `name`/`title`/`slug`; override it to regenerate unique fields.

## Reorder

For models with an order column:

```php
public function reorderable(): bool { return true; }
public function reorderColumn(): string { return 'position'; }   // the default
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

The list's free-text search (`?q=`) runs over the columns marked with
`search()`; override `searchableFields()` to choose them yourself. Without an
explicit order the list is sorted by `defaultOrder()` — newest first by the
primary key:

```php
public function searchableFields(): array { return ['title', 'slug']; }

public function defaultOrder(): array
{
    return [['column' => 'created_at', 'direction' => 'desc']];
}
```

## Relationships in fields/columns

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')
    ->searchable(['name', 'email']),

// Records of another resource, picked in a dialog with its search and filters
ResourcePicker::make('cover_id')->resource(MediaResource::class),
```

A list cell reads a flat key of the serialized row, so to show a related
value, load the relation in `indexQuery()` and expose the value as an
attribute (an accessor listed in the model's `$appends`):

```php
public function indexQuery(): Builder
{
    return parent::indexQuery()->with('author');
}

public function columns(): array
{
    return [TableColumn::make('author_name')->label('Author')];   // Article::getAuthorNameAttribute()
}
```

A resource is shown in pickers through `pickerItem()`: `recordTitle()`,
`recordSubtitle()` and `pickerPreview()` (an image URL or `null`). See
[ResourcePicker](../fields-reference.md#resourcepicker).

## See also

- [Screens](screens.md) — non-CRUD pages
- [Permissions](permissions.md) — RBAC details
- [Fields reference](../fields-reference.md)
- [Layouts reference](../layouts-reference.md)

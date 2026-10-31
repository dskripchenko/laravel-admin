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
public static function singularLabel(): ?string { return 'article'; } // one record
```

`singularLabel()` names one record, written as it reads mid-sentence. The panel
uses it wherever the plural label would read wrong: the create and edit page
titles ("Create article", "Edit article: Hello world"), the record crumb, the
Create button's tooltip, delete confirmations ("Delete this article?"), toasts
("Article created.") and empty states ("No articles yet. Create the first
article."). It reaches the SPA as `singular_label` in the manifest and is
translated per request through the JSON translations, like `label()`.

The default needs no configuration. An English label is singularized with
`Str::singular()` ("Blog Posts" → "blog post"; an all-caps word such as "API"
keeps its case). A label in another script gets `null`: Russian has no reliable
automatic singular, so the panel uses wording that needs none, for example
"Новая запись: Статьи" for the create page and "Редактирование: <record title>"
for the edit page. A singular that stays untranslated while `label()` is
translated is dropped too, so a Russian panel never reads "Создать: article".

The Russian source strings keep the singular in the nominative ("Создать:
статья") and leave it out where Russian would need another case, such as
"Удалить эту запись?". A translation may use `:singular`, `:Singular`, `:label`
and `:plural` even when the source does not; the English one does.

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

`asAudio(?string $disk = null, bool $waveform = false)` puts the kit's mini audio player into the cell — starting one row pauses the others, and a click on the player does not open the row. `asVideo(?string $disk = null, ?string $posterColumn = null, int $width = 64, ?int $height = null)` shows the poster (from another column of the row) or a plain tile with a play button and opens the player in a modal; nothing is downloaded before the click. The value is a URL, a storage path (with `$disk`) or an uploaded file.

```php
TableColumn::make('recording')->asAudio('local'),
TableColumn::make('clip_path')->asVideo('public', posterColumn: 'cover_url'),
```

`asBadge()` maps a value to a tone (`info`, `success`, `warning`, `danger`,
`default`, or the colour names `green`, `red`, `yellow`, `blue`, `gray`) and,
when the stored value is not what people should read, to a label:

```php
TableColumn::make('status')->asBadge([
    'draft' => ['label' => 'Draft', 'tone' => 'warning'],
    'published' => ['label' => 'Published', 'tone' => 'success'],
]),
// or the tones and the labels apart:
TableColumn::make('status')->asBadge(['draft' => 'warning'], ['draft' => 'Draft']),
```

Labels are translated like any other caption; a value with no label is shown
as it is.

`format(callable)` runs on the server while the rows are serialized — a
resource's list and tree, a `TableWidget` — as `fn ($value, array $row)`, and
the cell shows what it returns:

```php
TableColumn::make('author_id')->format(fn ($id, array $row) => $row['author']['name'] ?? '—'),
```

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

## Kanban and calendar views

Next to the list (or the tree) the index page can show a resource's records
as a kanban board or a calendar. Declare the views you want; a switcher
(list | kanban | calendar) appears above the page, the chosen view goes into
the URL (`?view=kanban`) and into saved views, and the search and filters
apply to every view.

### Kanban

```php
use Dskripchenko\LaravelAdmin\Resource\Views\KanbanView;

public function kanban(): ?KanbanView
{
    return KanbanView::make('status')                  // the column per value
        ->columns(['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done'])
        ->title('name')
        ->description('summary')
        ->tags('labels')                               // an array or "a, b, c"
        ->assignee('owner.name')                       // a dot path over a relation
        ->due('due_at')                                // overdue cards are marked
        ->lanes('priority')                            // optional swimlanes
        ->wip(['doing' => 5])                          // work-in-progress limits
        ->limit(50);                                   // cards per column per load
}
```

- `columns()` takes a map of value ⇒ label, a list of `{value, label}` or an
  enum class (backed values; the label is the case name, as
  `Select::fromEnum()` names it). Without it the columns are the options of
  the form field of the same name, else the distinct values the records hold.
  `lanes()` takes its labels the same way, as a second argument.
- The dot paths (`owner.name`) are eager-loaded. The title is
  `recordTitle()` unless `title()` says otherwise.
- Each column loads `limit()` cards; its header shows the total, the WIP limit
  and a "More" button for the next page.
- Dragging a card to another column writes its value to the status column (and
  the lane's value to the lane column). On a reorderable resource the cards
  are ordered by `reorderColumn()` (or `orderBy()`), and a drop inside a
  column is saved the way the list's drag-reorder saves it: the column's
  records trade the positions they hold. A user without the `reorder`
  permission still moves cards between columns; the order is then kept.
- `readonly()` turns the dragging off; so does a user without the `update`
  permission. A card whose `editableForRow($record, 'status')` is false stays
  in its column.

### Calendar

```php
use Dskripchenko\LaravelAdmin\Resource\Views\CalendarView;

public function calendar(): ?CalendarView
{
    return CalendarView::make('starts_at', 'ends_at')  // date or datetime columns
        ->allDay('all_day')                            // optional boolean column
        ->title('name')
        ->color('type', ['meeting' => 'primary', 'deadline' => 'danger'])
        ->views(['month', 'week', 'day', 'agenda'])    // and 'year'
        ->initialView('month');
}
```

- Without `allDay()` an event is all-day when the start column holds dates
  (a `date` cast, or a stored `Y-m-d`). The end of an all-day event is its
  last day, inclusive. Without an end column an event is a point in time.
- `color()` maps a column's values to a tone (`primary`, `success`,
  `warning`, `danger`, `info`, `neutral`) or a CSS colour.
- The calendar loads the records whose interval meets the visible days, at
  most 1000 per request.
- Dragging an event moves it; in the day and week grids stretching its bottom
  edge changes its end. A click opens the record. For a user who may create,
  a click on an empty day of the month grid (or on a slot of the day and week
  grids, when the calendar is not read-only) opens the create form with the
  start column prefilled through its query.
- `readonly()` turns the dragging off; so does a user without the `update`
  permission.

### The checks of a move

A move takes the path of an inline edit: the route requires the `update`
permission; `editableForRow()` must allow every column it writes; the board's
value must be one of its columns (and the lane one of its lanes), with the
column's inline-edit rules when it has them; the dates must be valid with the
end not before the start. The record is saved through the model, so its
events fire and the audit log (the `Loggable` trait) records the change. A
refusal answers 403 or 422 in the API's error shape, and the panel puts the
card or event back.

The endpoints: `POST {slug}/board`, `{slug}/boardMove`, `{slug}/calendar`,
`{slug}/calendarMove` — see the [API reference](../api-reference.md).

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

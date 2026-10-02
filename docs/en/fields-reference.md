---
title: Fields Reference
audience: developer
status: stable
locale: en
---

# Fields Reference

All fields extend `Dskripchenko\LaravelAdmin\Field\Field`. They share:

```php
->required()           // adds 'required' to validation rules
->placeholder('text')
->help('Hint shown under the field')
->title('Custom label') // default: the name made readable, `opens_at` → "Opens At"
->default('value')     // initial form-state
->onCreate(false)      // hide on the create form; also onUpdate(), onView()
->visibleWhen('driver', 's3')          // show only while another field holds a value
->visibleWhen('driver', ['s3', 'minio']) // ...or any of several values
->span(6)              // width in the 12-column grid of a Rows layout
->canSee(fn () => Gate::allows('manage-billing')) // server-side visibility
->readonly()
->disabled()
->rules(['min:3', 'max:255'])  // explicit Laravel rules
```

`rules()` replaces the explicit rules set before it; `required()` is kept
either way. On top of the explicit rules every field adds implicit ones from its
type: `numeric`/`integer` and `min`/`max` for numbers, `email` for
`->type('email')`, `date` for dates, `array` for multiple choices, and so on —
so the limits are declared once, on the field.

Methods a field class does not define are stored as attributes and passed to
the SPA component as props. That is how `->placeholder()`, `->type()` or
`->rows()` work, and also why a misspelt method fails silently: it becomes an
attribute nobody reads.

## Text inputs

### Input

```php
Input::make('title')->required(),
Input::make('email')->type('email'),
Input::make('phone')->type('tel'),
Input::make('website')->type('url'),
```

For a password use `Password` (below), for a color `ColorPicker`.

### Textarea

```php
Textarea::make('description')->rows(6),
```

### Number

```php
Number::make('price')->min(0)->max(99999)->step(0.01),
Number::make('quantity')->integer(),
```

### Slug

A URL slug derived from another field of the form:

```php
Slug::make('slug')->from('title')->separator('-'),
```

`from()` names the source field. The SPA fills the slug as the source
changes, until it is edited by hand; clearing it hands it back to the source.
A saved slug that no longer matches its source is left alone. With
`reactive(false)` only an empty slug is filled: a new record's slug follows
the source until edited, a saved record's slug is never rewritten.

The conversion is `Slug::generate($title, $separator)`, and the SPA runs a
port of it over the same transliteration table, so the suggested slug is the
one the server would produce: Cyrillic (Russian, Ukrainian, Belarusian,
Serbian, Kazakh), Greek and Latin letters with diacritics are transliterated
(`Щука и ёж` → `shchuka-i-yozh`), whitespace, dashes and `_` become the
separator, `@` becomes `at`, other characters are dropped. Use
`Slug::generate()` on the server where no form is involved — an import, a
seeder, a save hook.

### Code

Code editor with syntax highlighting:

```php
Code::make('snippet')->language('javascript')->lineNumbers()->height(400),
```

`theme()` is accepted but ignored: the editor follows the panel's theme.

### Markdown

Markdown editor with live preview:

```php
Markdown::make('content')->height('300px'),
Markdown::make('notes')->preview(false)->toolbar(false),
```

### Wysiwyg

Default editor: `@dskripchenko/wysiwyg` (no dependencies).

```php
Wysiwyg::make('body'),
Wysiwyg::make('summary')->preset('minimal'),     // 'minimal' | 'default' | 'full'
Wysiwyg::make('page')->preset('full')->uploadImages(),
```

The HTML is sanitized on save by default; `->sanitize(false)` turns that off for
content you trust.

Quill and TinyMCE adapters ship in the npm package as subpath exports; the host
installs the editor itself (`quill` + `@vueup/vue-quill`, or `tinymce` +
`@tinymce/tinymce-vue`) and registers the component under the `wysiwyg` type:

```ts
import { registerField } from '@dskripchenko/laravel-admin'
import { QuillField } from '@dskripchenko/laravel-admin/quill'
// or: import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'

registerField('wysiwyg', QuillField)
```

## Selection

### Select

```php
Select::make('status')->options([
    'draft' => 'Draft',
    'published' => 'Published',
])->required(),
```

Options also come from an enum or a model:

```php
Select::make('status')->fromEnum(ArticleStatus::class),
Select::make('category_id')->fromModel(Category::class, 'id', 'title'),
Select::make('country')->options([...])->searchable()->clearable(),
```

### Combobox

A select that also accepts a value outside its list — the options are hints,
not a rule. Use `Select` where the set really is closed.

```php
Combobox::make('model')
    ->options(['claude-opus-5' => 'Claude Opus 5', 'claude-sonnet-5' => 'Claude Sonnet 5'])
    ->clearable(),
```

`creatable()` is on by default; `->creatable(false)` restricts it to the list.

### Radio

```php
Radio::make('plan')->options([...])->inline(),
```

### Checkbox / Switch

```php
Checkbox::make('agree')->title('I agree'),
Switcher::make('is_active')->title('Active'),
Switcher::make('published')->title('Status')->labels('Published', 'Draft'),
```

`Switcher` is a toggle switch. With `labels($on, $off)` the caption next to
it follows the state, and the field's title sits above.

## Date / time

```php
DatePicker::make('start_date'),
DatePicker::make('birthday')->min('1900-01-01')->max(now()),
DatePicker::make('publish_at')->withTime(),
DateRange::make('period')->presets(['today', 'last_7_days']),
TimePicker::make('start_time')->step(15),
```

`withTime()` adds a time picker next to the date and switches the stored
format to `Y-m-d H:i:s`. Another `format()` decides how the value is written:
`Y-m-d H:i` drops the seconds, `Y-m-d\TH:i:s` joins with a `T`. A time picked
before the date is kept until the date is chosen; a missing time is midnight. `DateRange` stores
`{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`.

## Numeric

```php
Slider::make('volume')->min(0)->max(100)->step(5)->marks([0 => 'Off', 100 => 'Max']),
Rating::make('quality')->count(5)->half(),
```

## Files

```php
FileUpload::make('contract')
    ->maxSize(5 * 1024)        // KB
    ->accept(['application/pdf', '.docx']),

FileUpload::make('avatar')->image(),   // image-only, with a preview

ImageCropper::make('hero')
    ->aspectRatio(16 / 9)
    ->minCrop(800, 450)
    ->outputSize(1600, 900)
    ->quality(0.85),
```

The SPA uploads the file first, through the panel's `uploads` endpoint, and
puts `{disk, path, url, name, size, mime}` into the form state. The disk and the
directory come from `config('admin.uploads.disk')` and
`config('admin.uploads.directory')`. One file per field: `multiple()` and
`maxFiles()` shape the validation rules, but the SPA's uploader handles a single
file.

## Relations

### RelationSelect

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')   // model, label column, value column = 'id'
    ->preload(['team']),
```

The options are loaded from the related model when the form is serialized (up
to 100 rows; `->eager(500)` raises the limit), so this suits reference tables.
For a large table use `ResourcePicker`.

### RelationTable

A read-only table of related records on the edit form — a HasMany or a
BelongsToMany. The rows come from the field's value, so load the relation with
the record:

```php
RelationTable::make('items')
    ->relation('items')
    ->columns([
        TableColumn::make('name')->label('Name'),
        TableColumn::make('price')->asMoney('USD'),
    ]),
```

The columns are `TableColumn`s, with the same presets as a resource list.

### ResourcePicker

Records of another registered resource, picked in a dialog. Unlike
`RelationSelect`, which reads a model into a select, the picker goes through
the target **resource**: the dialog lists what its index lists, with its search,
filters and pagination, and only for users who hold the target's
`admin.{slug}.view` permission.

```php
ResourcePicker::make('cover_id')
    ->resource(MediaResource::class),       // or a slug: 'media-library'

ResourcePicker::make('related_ids')
    ->resource('products')
    ->multiple()                            // an ordered list of keys
    ->maxItems(5)
    ->filters(['status' => 'active'])       // fixed, hidden from the toolbar
    ->perPage(24)
    ->layout('list')                        // 'grid' | 'list'; default: grid when records have previews
    ->dialogSize('xl'),                     // 'lg' | 'xl' | 'full'
```

The value is the record's key, or with `multiple()` a list of keys in the order
the user arranged them; cast a multiple picker's column to `array`. On save
every key must name a record of the target's `indexQuery()`, so scoping the
index scopes what can be attached.

The dialog draws each record from `Resource::pickerItem()`: the title from
`recordTitle()`, the subtitle from `recordSubtitle()` and the preview image
from `pickerPreview()`. Override them on the target resource:

```php
public function pickerPreview(Model $row): ?string
{
    return $row->avatar_url;
}
```

`uploadTo()` adds an upload button to the dialog. The file is posted as
multipart data to a path under the panel's API; the response is the new record
(or holds it under `responseKey`), and it gets selected:

```php
ResourcePicker::make('document_id')
    ->resource('documents')
    ->uploadTo('/documents/files/upload', permission: 'admin.documents.create',
        fileField: 'file', responseKey: 'document', data: ['folder' => 'contracts'],
        accept: 'application/pdf'),
```

The button is shown to users holding `permission` — the target's `create`
permission by default. On the view page the field shows the picked records
with their previews, each linking to the target's view page.

### MorphSwitcher

For polymorphic relations:

```php
MorphSwitcher::make('subject')
    ->morph('article', Article::class, 'title')
    ->morph('product', Product::class),          // display column 'name' by default

// or several at once, alias => model:
MorphSwitcher::make('subject')->morphMany([
    'article' => Article::class,
    'product' => Product::class,
]),
```

The state is `{type, id}`. Each type's records (up to 100) are loaded into the
form as options.

## Composite

### Repeater

Variable-length list of sub-forms:

```php
Repeater::make('tags')
    ->fields([
        Input::make('name')->required(),
        Input::make('color'),
    ])
    ->minItems(0)->maxItems(10)
    ->defaultItem(['color' => '#888888']),
```

`addable()`, `removable()` and `reorderable()` toggle the buttons.

### Group

Nested fields stored as one object in the state — `contact.email`,
`contact.phone` — and validated as an array:

```php
Group::make('contact')
    ->title('Contact info')
    ->fields([
        Input::make('email'),
        Input::make('phone'),
    ])
    ->layout('columns')        // 'rows' (default) | 'columns' | 'inline'
    ->collapsed(),
```

For purely visual grouping use a layout such as `Layout::block()` — see the
[layouts reference](layouts-reference.md).

### KeyValue

Free-form key/value pairs:

```php
KeyValue::make('headers')
    ->keyLabel('Header')
    ->valueLabel('Value')
    ->allowedKeys(['Accept', 'Authorization']),  // optional
```

### TagsInput

```php
TagsInput::make('tags')
    ->suggestions(['php', 'vue', 'laravel'])
    ->maxItems(8),
```

Suggestions do not restrict input: any string can be added.

## Tree / hierarchy

### TreeSelect

```php
TreeSelect::make('category_id')
    ->tree([
        ['value' => 1, 'label' => 'Electronics', 'children' => [
            ['value' => 2, 'label' => 'Phones'],
        ]],
    ])
    ->multiple(),

// or from a self-referencing model:
TreeSelect::make('category_id')
    ->fromModel(Category::class, 'parent_id', 'id', 'name')
    ->selectableParents(false),   // leaves only
```

### Cascader

Cascading dropdown for nested options:

```php
Cascader::make('location')
    ->options([
        ['value' => 'us', 'label' => 'USA', 'children' => [...]],
    ]),
```

## Special

### TranslatableInput

Tabs per locale; the state is `{en: '...', ru: '...'}`:

```php
TranslatableInput::make('title')->locales(['en', 'ru', 'de']),
TranslatableInput::make('body')->multiline()->locales(['en', 'ru']),
TranslatableInput::make('name')->requireAllLocales(),
```

Without `locales()` the list comes from `config('admin.ui.available_locales')`.
See [i18n](concepts/i18n.md).

### Builder

Page-builder style block list (for CMS hosts). Each block type is declared
with its own fields; the state is a list of `{type, data}`:

```php
Builder::make('blocks')
    ->block('hero', [
        Input::make('title')->required(),
        Markdown::make('subtitle'),
    ], label: 'Hero', icon: 'image')
    ->block('gallery', [
        FileUpload::make('image')->image(),
    ])
    ->maxBlocks(20),
```

### Hidden

```php
Hidden::make('uuid'),
```

### Label

Read-only display only — neither editable nor submitted. Shows the state's
value for its name, or a fixed `->value()`:

```php
Label::make('id')->title('Record ID'),
Label::make('note')->value('Changes apply after a restart.'),
```

### ColorPicker

```php
ColorPicker::make('brand_color')
    ->format('hex')                       // 'hex' | 'rgb' | 'hsl'
    ->palette(['#1e88e5', '#43a047'])
    ->withAlpha(),
```

### Password

```php
Password::make('password')->required()->confirmed()->revealable(),
```

`confirmed()` adds the `confirmed` rule, so the form also needs a
`password_confirmation` field.

### Generated

A random string generated in the browser when the create form opens — tokens,
secret keys — with a "Generate" button:

```php
Generated::make('api_key')->length(40)->charset('abcdef0123456789'),
```

## See also

- [Resources](concepts/resources.md)
- [Layouts reference](layouts-reference.md)
- [Frontend extension](frontend-extension.md) — register custom field

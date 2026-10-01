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
->title('Custom label')
->default('value')     // initial form-state
->visibleOn(['create', 'update'])
->hiddenOn(['view'])
->reactive(['title' => 'slugify'])  // recompute when other field changes
->readonly()
->rules(['min:3', 'max:255'])  // additional Laravel rules
```

## Text inputs

### Input

```php
Input::make('title')->required(),
Input::make('email')->type('email'),
Input::make('phone')->type('tel'),
Input::make('website')->type('url'),
Input::make('password')->type('password'),
Input::make('color')->type('color'),
```

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

Auto-fills from a source field:

```php
Slug::make('slug')->from('title'),
```

### Code

Code editor with syntax highlighting:

```php
Code::make('snippet')->language('javascript')->theme('dark'),
```

### Markdown

Markdown editor with live preview:

```php
Markdown::make('content')->minHeight('300px'),
```

### Wysiwyg

Default: `@dskripchenko/wysiwyg` (zero-dep, ~12 KB gz).

```php
Wysiwyg::make('body')->sanitize(),
```

Override per-host: `registerField('wysiwyg', QuillField)` (sister-pack
`dskripchenko/laravel-admin-quill` or `-tinymce`).

## Selection

### Select

```php
Select::make('status')->options([
    'draft' => 'Draft',
    'published' => 'Published',
])->required(),
```

### Combobox

Searchable Select with async options:

```php
Combobox::make('category_id')
    ->source('/api/categories/search')
    ->searchable(),
```

### Radio

```php
Radio::make('plan')->options([...])->inline(),
```

### Checkbox / Switch

```php
Checkbox::make('agree')->title('I agree'),
Switcher::make('is_active')->title('Active'),
```

## Date / time

```php
DatePicker::make('start_date'),
DatePicker::make('publish_at')->withTime(),
DateRangePicker::make('period'),
TimePicker::make('start_time'),
```

## Numeric

```php
Slider::make('volume')->min(0)->max(100)->step(5)->showValue(),
Rating::make('quality')->max(5)->allowHalf(),
```

## Files

```php
FileUpload::make('avatar')
    ->disk('public')
    ->path('avatars')
    ->maxSize(5 * 1024)        // KB
    ->accept(['image/*'])
    ->multiple(),

ImageCropper::make('hero')
    ->aspectRatio(16 / 9)
    ->minSize(800, 450),
```

## Relations

### RelationSelect

```php
RelationSelect::make('author_id')
    ->relation('author')
    ->display('name')
    ->searchable(),
```

### RelationTable

For has-many editing inline:

```php
RelationTable::make('items')
    ->relation('items')
    ->columns(['name', 'price'])
    ->editable(),
```

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
    ->types([
        Article::class => 'Article',
        Product::class => 'Product',
    ]),
```

## Composite

### Repeater

Variable-length list of sub-forms:

```php
Repeater::make('tags')
    ->fields([
        Input::make('name')->required(),
        Input::make('color'),
    ])
    ->minItems(0)->maxItems(10),
```

### Group

Logical grouping (no array structure, just visual):

```php
Group::make('contact')
    ->title('Contact info')
    ->fields([
        Input::make('email'),
        Input::make('phone'),
    ]),
```

### KeyValue

Free-form key/value pairs:

```php
KeyValue::make('headers')
    ->keyLabel('Header')
    ->valueLabel('Value'),
```

### TagsInput

```php
TagsInput::make('tags')
    ->suggestions(['php', 'vue', 'laravel'])
    ->maxItems(8),
```

## Tree / hierarchy

### TreeSelect

```php
TreeSelect::make('category_id')
    ->options($treeOptions)
    ->multiple(),
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

Tabs per locale:

```php
TranslatableInput::make('title')->locales(['en', 'ru', 'de']),
TranslatableInput::make('body')->multiline()->locales(['en', 'ru']),
```

### Builder

Page-builder style block list (for CMS hosts):

```php
Builder::make('blocks')->blocks([
    HeroBlock::class, TextBlock::class, GalleryBlock::class,
]),
```

### Hidden

```php
Hidden::make('uuid'),
```

### Label

Read-only display only:

```php
Label::make('id')->title('Record ID'),
```

## See also

- [Resources](concepts/resources.md)
- [Layouts reference](layouts-reference.md)
- [Frontend extension](frontend-extension.md) — register custom field

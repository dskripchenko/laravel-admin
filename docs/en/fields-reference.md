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

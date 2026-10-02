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

См. [каталог полей](../../en/fields-reference.md) (en) для всех типов.

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
TableColumn::make('id')->sort(),
TableColumn::make('title')->sort()->search(),
TableColumn::make('status')->asBadge(['published' => 'success'])->align('center'),
TableColumn::make('created_at')->asDateTime()->sort(),
TableColumn::make('price')->asMoney('USD')->align('right'),
TableColumn::make('cover')->asImage(),
```

Форматы: `asDate()`, `asDateTime()`, `asMoney()`, `asBoolean()`,
`asBytes()`, `asBadge()`, `asLink()`, `asImage()`, а для остального —
`format(callable)`. Действия над строкой таблица рисует сама.

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

### Выбор записей другого ресурса

`ResourcePicker` открывает диалог со списком записей целевого ресурса — с его
поиском, фильтрами, пагинацией и правом `admin.{slug}.view`. Значение — ключ
записи, а с `multiple()` — упорядоченный список ключей. При сохранении каждый
ключ проверяется по `indexQuery()` целевого ресурса.

```php
ResourcePicker::make('cover_id')->resource(MediaResource::class),
ResourcePicker::make('related_ids')->resource('products')->multiple()->maxItems(5),
```

Как запись выглядит в диалоге, задаёт целевой ресурс: заголовок —
`recordTitle()`, подпись — `recordSubtitle()`, превью — `pickerPreview()`
(URL картинки или `null`). Все три собирает `pickerItem()`.

```php
public function pickerPreview(Model $row): ?string
{
    return $row->avatar_url;
}
```

Остальные настройки (`filters()`, `uploadTo()`, `layout()`, `dialogSize()`) —
в [каталоге полей](../../en/fields-reference.md#resourcepicker) (en).

## См. также

- [Screens](screens.md)
- [Permissions](../../en/concepts/permissions.md) (en)
- [Каталог полей](../../en/fields-reference.md) (en)

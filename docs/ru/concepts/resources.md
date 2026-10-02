---
title: Resources
audience: developer
status: stable
locale: ru
translated_from: en/concepts/resources.md
translated_at: 2026-10-02
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
public static function label(): string { return 'Статьи'; }   // подпись в sidebar
```

## Fields

Каждое поле возвращает дескриптор. Типичные варианты:

```php
Input::make('email')->type('email')->required()->placeholder('user@host'),
Number::make('price')->min(0)->step(0.01),
Select::make('status')->options(['draft' => 'Черновик', 'published' => 'Опубликовано'])->required(),
DatePicker::make('published_at')->withTime(),
RelationSelect::make('category_id')->relation(Category::class, 'name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

Все типы — в [каталоге полей](../fields-reference.md).

### Видимость по режимам

```php
Input::make('slug')->required()->onView(false),     // только create + update
Password::make('password')->onUpdate(false),         // только create
```

`onCreate()`, `onUpdate()` и `onView()` принимают bool, по умолчанию
`true`: поле, которому ничего не задано, видно во всех трёх режимах. Чтобы
показывать поле, только пока другое поле имеет нужное значение, —
`visibleWhen('driver', 's3')`.

### Поля, вычисляемые из других полей

```php
Slug::make('slug')->from('title'),
```

Фронтенд пересчитывает `slug` при каждом изменении `title` — пока slug не
отредактировали вручную. Для более сложных случаев — опции, зависящие от
другого поля, вычисляемая сумма — оберните поля в
[Listener](../layouts-reference.md#listener-реактивная-часть-формы).

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

`asBadge()` сопоставляет значению тон (`info`, `success`, `warning`, `danger`,
`default` или названия цветов `green`, `red`, `yellow`, `blue`, `gray`) и,
когда хранимое значение не для чтения людьми, — подпись:

```php
TableColumn::make('status')->asBadge([
    'draft' => ['label' => 'Черновик', 'tone' => 'warning'],
    'published' => ['label' => 'Опубликовано', 'tone' => 'success'],
]),
// или тона и подписи отдельно:
TableColumn::make('status')->asBadge(['draft' => 'warning'], ['draft' => 'Черновик']),
```

Подписи переводятся как любые подписи; значение без подписи показывается как
есть.

`format(callable)` выполняется на сервере при сериализации строк — в списке и
дереве ресурса, в `TableWidget` — как `fn ($value, array $row)`, и ячейка
показывает то, что он вернул:

```php
TableColumn::make('author_id')->format(fn ($id, array $row) => $row['author']['name'] ?? '—'),
```

## Filters

```php
public function filters(): array
{
    return [
        InputFilter::for('title')->label('Заголовок'),           // LIKE %…%
        OptionsFilter::for('status')->options(['draft' => 'Черновик', 'published' => 'Опубликовано']),
        DateRangeFilter::for('created_at'),                       // {from, to}
        SelectFromModelFilter::for('category_id')->fromModel(Category::class, 'name'),
        SwitcherFilter::for('is_featured'),
    ];
}
```

Фильтры лежат в `Dskripchenko\LaravelAdmin\Filter`. Для модели с
`SoftDeletes` `TrashedFilter` добавляется автоматически. Всё, что не покрывают
остальные, решает `QueryFilter` с замыканием:
`QueryFilter::for('legacy_status')->using(fn ($q, $value) => $q->where(...))`.

## Actions

Действия строки, bulk и command-bar — см. [Actions](actions.md).

```php
public function actions(): array
{
    return [
        Button::make('Опубликовать')->withName('publish')->method('publish')->position(['row']),
        BulkAction::make('В архив')->withName('archive')->method('archive')->confirm('Архивировать N статей?'),
    ];
}

public function publish(array $ids, array $payload = []): void
{
    \App\Models\Article::whereKey($ids)->update(['status' => 'published']);
}
```

Действие вызывается по имени, а метод ресурса получает выбранные id и
payload действия. Имя выводится из подписи, но только из латиницы и цифр:
у кириллической подписи оно вырождается в `action`, и несколько таких
действий сливаются в одно. Поэтому при русских подписях задавайте имя явно —
`withName()`.

## Soft-delete / Restore / Force-delete

Если модель использует `SoftDeletes`, админка сама включает:
- `TrashedFilter` (активные / удалённые / все)
- действие строки `Restore`
- действие строки `ForceDelete` (под правом `admin.{slug}.force-delete`)

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

По умолчанию `replicate()` — это `Model::replicate()` из Eloquent плюс
суффикс копии у `name`/`title`/`slug`; переопределите, чтобы заново
сгенерировать уникальные поля.

## Reorder

Для моделей с колонкой порядка:

```php
public function reorderable(): bool { return true; }
public function reorderColumn(): string { return 'position'; }   // значение по умолчанию
```

В списке появляется колонка с drag-handle.

## Permissions

Базовое право по умолчанию — `admin.{slug}`. Из него выводятся: `view`,
`create`, `update`, `delete`, `restore`, `force-delete`, `replicate`,
`reorder`. Переопределить:

```php
public static function permission(): string
{
    return 'admin.articles';     // admin.articles.view, admin.articles.update, ...
}
```

## Searchable / Sortable

Полнотекстовый поиск списка (`?q=`) идёт по колонкам, помеченным `search()`;
чтобы выбрать их самому, переопределите `searchableFields()`. Без явной
сортировки список упорядочен по `defaultOrder()` — новые сверху по
первичному ключу:

```php
public function searchableFields(): array { return ['title', 'slug']; }

public function defaultOrder(): array
{
    return [['column' => 'created_at', 'direction' => 'desc']];
}
```

## Связи в полях/колонках

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')
    ->searchable(['name', 'email']),
```

Ячейка списка читает плоский ключ сериализованной строки. Чтобы показать
значение из связи, загрузите связь в `indexQuery()` и отдайте значение
атрибутом (accessor, перечисленный в `$appends` модели):

```php
public function indexQuery(): Builder
{
    return parent::indexQuery()->with('author');
}

public function columns(): array
{
    return [TableColumn::make('author_name')->label('Автор')];   // Article::getAuthorNameAttribute()
}
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
в [каталоге полей](../fields-reference.md#resourcepicker).

## См. также

- [Screens](screens.md) — non-CRUD страницы
- [Permissions](permissions.md) — подробности RBAC
- [Каталог полей](../fields-reference.md)
- [Каталог layout'ов](../layouts-reference.md)

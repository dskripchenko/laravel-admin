---
title: Каталог полей
audience: developer
status: stable
locale: ru
translated_from: en/fields-reference.md
translated_at: 2026-10-02
---

# Каталог полей

Все поля наследуют `Dskripchenko\LaravelAdmin\Field\Field`. Общие методы:

```php
->required()           // добавляет 'required' в правила валидации
->placeholder('text')
->help('Подсказка под полем')
->title('Своя подпись') // по умолчанию — имя в читаемом виде: `opens_at` → «Opens At»
->default('value')     // начальное состояние формы
->onCreate(false)      // скрыть на форме создания; также onUpdate(), onView()
->visibleWhen('driver', 's3')          // показывать, пока другое поле равно значению
->visibleWhen('driver', ['s3', 'minio']) // ...или любому из нескольких
->span(6)              // ширина в 12-колоночной сетке layout'а Rows
->canSee(fn () => Gate::allows('manage-billing')) // видимость на стороне сервера
->readonly()
->disabled()
->rules(['min:3', 'max:255'])  // явные правила Laravel
```

`rules()` заменяет ранее заданные явные правила; `required()` при этом
сохраняется. Поверх явных правил каждое поле добавляет неявные, по своему типу:
`numeric`/`integer` и `min`/`max` для чисел, `email` для `->type('email')`,
`date` для дат, `array` для множественного выбора и так далее — ограничения
объявляются один раз, на самом поле.

Методы, которых нет в классе поля, сохраняются как атрибуты и передаются
компоненту SPA как props. Так работают `->placeholder()`, `->type()` или
`->rows()` — и поэтому же опечатка в имени метода не даёт ошибки: она просто
становится атрибутом, который никто не читает.

## Текстовые поля

### Input

```php
Input::make('title')->required(),
Input::make('email')->type('email'),
Input::make('phone')->type('tel'),
Input::make('website')->type('url'),
```

Для пароля используйте `Password` (ниже), для цвета — `ColorPicker`.

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

URL-slug, производный от другого поля формы:

```php
Slug::make('slug')->from('title')->separator('-'),
```

`from()` указывает поле-источник. Сейчас SPA рисует slug как обычное текстовое
поле и сама его не заполняет; чтобы получить slug на сервере, используйте
`Slug::generate($title)` (обёртка над `Str::slug`) — например, в хуке
сохранения ресурса.

### Code

Редактор кода с подсветкой синтаксиса:

```php
Code::make('snippet')->language('javascript')->lineNumbers()->height(400),
```

`theme()` принимается, но игнорируется: редактор следует теме панели.

### Markdown

Markdown-редактор с живым предпросмотром:

```php
Markdown::make('content')->height('300px'),
Markdown::make('notes')->preview(false)->toolbar(false),
```

### Wysiwyg

Редактор по умолчанию — `@dskripchenko/wysiwyg` (без зависимостей).

```php
Wysiwyg::make('body'),
Wysiwyg::make('summary')->preset('minimal'),     // 'minimal' | 'default' | 'full'
Wysiwyg::make('page')->preset('full')->uploadImages(),
```

HTML по умолчанию санитизируется при сохранении; `->sanitize(false)` отключает
это для контента, которому вы доверяете.

Адаптеры Quill и TinyMCE входят в npm-пакет как subpath-экспорты; сам редактор
хост ставит сам (`quill` + `@vueup/vue-quill` или `tinymce` +
`@tinymce/tinymce-vue`) и регистрирует компонент под типом `wysiwyg`:

```ts
import { registerField } from '@dskripchenko/laravel-admin'
import { QuillField } from '@dskripchenko/laravel-admin/quill'
// или: import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'

registerField('wysiwyg', QuillField)
```

## Выбор

### Select

```php
Select::make('status')->options([
    'draft' => 'Черновик',
    'published' => 'Опубликовано',
])->required(),
```

Варианты можно взять и из enum или модели:

```php
Select::make('status')->fromEnum(ArticleStatus::class),
Select::make('category_id')->fromModel(Category::class, 'id', 'title'),
Select::make('country')->options([...])->searchable()->clearable(),
```

### Combobox

Select, который принимает и значение вне списка: варианты — подсказки, а не
ограничение. Где набор действительно закрыт, используйте `Select`.

```php
Combobox::make('model')
    ->options(['claude-opus-5' => 'Claude Opus 5', 'claude-sonnet-5' => 'Claude Sonnet 5'])
    ->clearable(),
```

`creatable()` включён по умолчанию; `->creatable(false)` ограничивает выбор
списком.

### Radio

```php
Radio::make('plan')->options([...])->inline(),
```

### Checkbox / Switch

```php
Checkbox::make('agree')->title('Я согласен'),
Switcher::make('is_active')->title('Активен'),
```

## Дата и время

```php
DatePicker::make('start_date'),
DatePicker::make('birthday')->min('1900-01-01')->max(now()),
DatePicker::make('publish_at')->withTime(),
DateRange::make('period')->presets(['today', 'last_7_days']),
TimePicker::make('start_time')->step(15),
```

`withTime()` переключает формат хранения на `Y-m-d H:i:s`; пикер SPA пока
редактирует только дату. `DateRange` хранит
`{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`.

## Числовые

```php
Slider::make('volume')->min(0)->max(100)->step(5)->marks([0 => 'Выкл', 100 => 'Макс']),
Rating::make('quality')->count(5)->half(),
```

## Файлы

```php
FileUpload::make('contract')
    ->maxSize(5 * 1024)        // КБ
    ->accept(['application/pdf', '.docx']),

FileUpload::make('avatar')->image(),   // только изображения, с превью

ImageCropper::make('hero')
    ->aspectRatio(16 / 9)
    ->minCrop(800, 450)
    ->outputSize(1600, 900)
    ->quality(0.85),
```

SPA сначала загружает файл через эндпоинт панели `uploads` и кладёт в
состояние формы `{disk, path, url, name, size, mime}`. Диск и каталог берутся
из `config('admin.uploads.disk')` и `config('admin.uploads.directory')`. Один
файл на поле: `multiple()` и `maxFiles()` влияют на правила валидации, но
загрузчик SPA работает с одним файлом.

## Связи

### RelationSelect

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')   // модель, колонка подписи, колонка значения = 'id'
    ->preload(['team']),
```

Варианты загружаются из связанной модели при сериализации формы (до 100 строк;
`->eager(500)` поднимает лимит), поэтому поле подходит для справочников. Для
больших таблиц используйте `ResourcePicker`.

### RelationTable

Таблица связанных записей на форме редактирования, только для чтения —
HasMany или BelongsToMany. Строки берутся из значения поля, поэтому связь
нужно загрузить вместе с записью:

```php
RelationTable::make('items')
    ->relation('items')
    ->columns([
        TableColumn::make('name')->label('Название'),
        TableColumn::make('price')->asMoney('USD'),
    ]),
```

Колонки — это `TableColumn` с теми же пресетами, что и в списке ресурса.

### ResourcePicker

Записи другого зарегистрированного ресурса, выбираемые в диалоге. В отличие от
`RelationSelect`, который читает модель в select, пикер работает через целевой
**ресурс**: диалог показывает то же, что его список, — с его поиском,
фильтрами и пагинацией, и только пользователям с правом `admin.{slug}.view`
целевого ресурса.

```php
ResourcePicker::make('cover_id')
    ->resource(MediaResource::class),       // или slug: 'media-library'

ResourcePicker::make('related_ids')
    ->resource('products')
    ->multiple()                            // упорядоченный список ключей
    ->maxItems(5)
    ->filters(['status' => 'active'])       // фиксированные, скрыты из тулбара
    ->perPage(24)
    ->layout('list')                        // 'grid' | 'list'; по умолчанию grid, если у записей есть превью
    ->dialogSize('xl'),                     // 'lg' | 'xl' | 'full'
```

Значение — ключ записи, а с `multiple()` — список ключей в том порядке, в
каком их расставил пользователь; колонку множественного пикера кастуйте к
`array`. При сохранении каждый ключ должен указывать на запись из
`indexQuery()` целевого ресурса — ограничив список, вы ограничиваете и то, что
можно привязать.

Каждую запись диалог рисует по `Resource::pickerItem()`: заголовок — из
`recordTitle()`, подзаголовок — из `recordSubtitle()`, картинка превью — из
`pickerPreview()`. Переопределите их в целевом ресурсе:

```php
public function pickerPreview(Model $row): ?string
{
    return $row->avatar_url;
}
```

`uploadTo()` добавляет в диалог кнопку загрузки. Файл отправляется как
multipart на путь в API панели; ответ — новая запись (или объект, где она лежит
под `responseKey`), и она сразу выбирается:

```php
ResourcePicker::make('document_id')
    ->resource('documents')
    ->uploadTo('/documents/files/upload', permission: 'admin.documents.create',
        fileField: 'file', responseKey: 'document', data: ['folder' => 'contracts'],
        accept: 'application/pdf'),
```

Кнопку видят пользователи с правом `permission` — по умолчанию это право
`create` целевого ресурса. На странице просмотра поле показывает выбранные
записи с превью, каждая ведёт на страницу просмотра в целевом ресурсе.

### MorphSwitcher

Для полиморфных связей:

```php
MorphSwitcher::make('subject')
    ->morph('article', Article::class, 'title')
    ->morph('product', Product::class),          // колонка подписи по умолчанию — 'name'

// или несколько сразу, alias => model:
MorphSwitcher::make('subject')->morphMany([
    'article' => Article::class,
    'product' => Product::class,
]),
```

Состояние — `{type, id}`. Записи каждого типа (до 100) загружаются в форму как
варианты выбора.

## Составные

### Repeater

Список подформ переменной длины:

```php
Repeater::make('tags')
    ->fields([
        Input::make('name')->required(),
        Input::make('color'),
    ])
    ->minItems(0)->maxItems(10)
    ->defaultItem(['color' => '#888888']),
```

`addable()`, `removable()` и `reorderable()` включают и выключают кнопки.

### Group

Вложенные поля, которые хранятся в состоянии одним объектом —
`contact.email`, `contact.phone` — и валидируются как массив:

```php
Group::make('contact')
    ->title('Контакты')
    ->fields([
        Input::make('email'),
        Input::make('phone'),
    ])
    ->layout('columns')        // 'rows' (по умолчанию) | 'columns' | 'inline'
    ->collapsed(),
```

Для чисто визуальной группировки используйте layout, например
`Layout::block()`, — см. [каталог layout'ов](layouts-reference.md).

### KeyValue

Произвольные пары ключ/значение:

```php
KeyValue::make('headers')
    ->keyLabel('Заголовок')
    ->valueLabel('Значение')
    ->allowedKeys(['Accept', 'Authorization']),  // необязательно
```

### TagsInput

```php
TagsInput::make('tags')
    ->suggestions(['php', 'vue', 'laravel'])
    ->maxItems(8),
```

Подсказки ввод не ограничивают: добавить можно любую строку.

## Деревья и иерархии

### TreeSelect

```php
TreeSelect::make('category_id')
    ->tree([
        ['value' => 1, 'label' => 'Электроника', 'children' => [
            ['value' => 2, 'label' => 'Телефоны'],
        ]],
    ])
    ->multiple(),

// или из модели со ссылкой на родителя:
TreeSelect::make('category_id')
    ->fromModel(Category::class, 'parent_id', 'id', 'name')
    ->selectableParents(false),   // только листья
```

### Cascader

Каскадные выпадающие списки для вложенных вариантов:

```php
Cascader::make('location')
    ->options([
        ['value' => 'us', 'label' => 'USA', 'children' => [...]],
    ]),
```

## Специальные

### TranslatableInput

Вкладка на каждую локаль; состояние — `{en: '...', ru: '...'}`:

```php
TranslatableInput::make('title')->locales(['en', 'ru', 'de']),
TranslatableInput::make('body')->multiline()->locales(['en', 'ru']),
TranslatableInput::make('name')->requireAllLocales(),
```

Без `locales()` список берётся из `config('admin.ui.available_locales')`.
См. [i18n](concepts/i18n.md).

### Builder

Список блоков в духе page-builder'а (для CMS). Каждый тип блока объявляется со
своими полями; состояние — список `{type, data}`:

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

Только отображение — не редактируется и не отправляется. Показывает значение
состояния под своим именем или фиксированный `->value()`:

```php
Label::make('id')->title('ID записи'),
Label::make('note')->value('Изменения вступят в силу после перезапуска.'),
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

`confirmed()` добавляет правило `confirmed`, поэтому в форме нужно и поле
`password_confirmation`.

### Generated

Случайная строка, которая генерируется в браузере при открытии формы создания,
— токены, секретные ключи — с кнопкой «Сгенерировать»:

```php
Generated::make('api_key')->length(40)->charset('abcdef0123456789'),
```

## См. также

- [Resources](concepts/resources.md)
- [Каталог layout'ов](layouts-reference.md)
- [Frontend-расширение](frontend-extension.md) — регистрация своего поля

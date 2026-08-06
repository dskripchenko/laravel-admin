# dskripchenko/laravel-admin-media

## 1. Назначение

Расширенная медиа-библиотека: коллекции, теги, focal-point, responsive-варианты, EXIF-стрипинг, browse-and-pick UX. Поверх (или вместо) простой таблицы `admin_attachments` из core. Без `spatie/laravel-medialibrary` в зависимостях.

**Use case:** контентные проекты, где одна и та же картинка используется на нескольких страницах, нужны теги/коллекции для поиска, нужен focal-point для безопасного crop'а под разные ratio, нужны responsive-варианты для `<img srcset>`.

## 2. Состав

### Модели и таблицы

- **`admin_media`** (`id, disk, path, mime, size, width, height, exif json, focal_x, focal_y, alt, title, description, collection, tags json, attachable_morph_*, uploader_id, created_at, updated_at`).
- **`admin_media_variants`** (`id, media_id, name, path, width, height, mime, size, format`) — производные (thumb, preview, w-768, w-1280, ...).
- **`admin_media_attachments`** (pivot) — `media_id, attachable_type, attachable_id, role, position` — для связи media с любой моделью через morphMany (одна Media в нескольких записях).

### Resource

- **`MediaLibraryResource`** — браузер всей библиотеки.
  - Grid-view (плитки 200×200 с превью + меta-overlay) и list-view (таблица с колонками).
  - Drag-and-drop bulk-upload в любую коллекцию.
  - Inline-edit `alt`, `title`, `tags`.
  - Фильтры: collection, type (image/video/audio/document), tags, uploader, size-range, dimensions.
  - Bulk: move-to-collection, add-tags, remove-tags, regenerate-variants, delete.
  - Permission: `admin.media.*`.

### Field

- **`Field\MediaPicker::make('avatar')`** — заменяет базовый `Field\FileUpload` в media-aware Resource'ах.
  - Открывает модалку со встроенным `MediaLibraryResource` (либо upload новый, либо выбор существующего).
  - Single (`->multi(false)`) и multi-select с сортировкой выбранных.
  - Опции: `->collection('articles')`, `->mimes(['image/*'])`, `->maxSize('5MB')`, `->aspectRatio(16/9)`, `->responsiveSet('content')`.

### Image processing

Без `intervention/image` в required:

- Своя обёртка `ImageProcessor` поверх PHP GD или Imagick (выбирается автоматически по доступности).
- Операции: resize, crop (focal-point-aware), quality, format-conversion (WebP/AVIF), EXIF-strip.
- Если установлен `intervention/image` (через `composer suggest`) — берём его как опциональный driver с расширенным feature-set'ом (water marks, advanced filters).

### Responsive-варианты

Описываются в `config/admin-media.php → responsive_sets`:

```php
'responsive_sets' => [
    'content' => [
        ['name' => 'thumb',  'width' => 200,  'format' => 'webp', 'quality' => 80],
        ['name' => 'w-768',  'width' => 768,  'format' => 'webp', 'quality' => 85],
        ['name' => 'w-1280', 'width' => 1280, 'format' => 'webp', 'quality' => 85],
        ['name' => 'w-1920', 'width' => 1920, 'format' => 'webp', 'quality' => 90],
    ],
    'avatar' => [
        ['name' => 'sm', 'width' => 64,  'crop' => true, 'aspect' => 1.0, 'format' => 'webp'],
        ['name' => 'md', 'width' => 128, 'crop' => true, 'aspect' => 1.0, 'format' => 'webp'],
    ],
],
```

Генерация — фоновая через `delayed-process` (handler `RegenerateVariantsProcess`). Загрузка возвращает оригинал, варианты дописываются по мере готовности; UI показывает `<UiProgress>` и заменяет thumb по WebSocket-нотификации (если включена) или по polling.

### Focal-point cropper

- При upload изображения по умолчанию focal-point = центр (`0.5, 0.5`).
- В edit-форме MediaResource — canvas-overlay с draggable-точкой, которая определяет «безопасную зону» при crop.
- При генерации варианта с crop=true crop-окно выбирается так, чтобы focal-point остался внутри.

### Helper'ы для Blade/Vue

```php
// PHP
$media->variant('w-768')->url();
$media->responsiveSrcset('content');         // строка для srcset

// Vue
<img :src="media.variant('thumb').url" :srcset="media.responsiveSrcset('content')" :alt="media.alt">
```

## 3. Зависимости

**Composer:**

```
"require": {
    "dskripchenko/laravel-admin": "^1.0",
    "ext-gd": "*"
},
"suggest": {
    "intervention/image": "Расширенный image processing (advanced filters, watermarks)",
    "ext-imagick": "Альтернатива GD с лучшим качеством и форматами"
}
```

NPM: нет (всё через core-компоненты).

## 4. Миграции

- `create_admin_media_table`
- `create_admin_media_variants_table`
- `create_admin_media_attachments_table`

## 5. Permissions

```php
ItemPermission::group('Медиа')
    ->addPermission('admin.media.view',     'Просмотр библиотеки')
    ->addPermission('admin.media.upload',   'Загрузка')
    ->addPermission('admin.media.update',   'Редактирование (alt, title, focal)')
    ->addPermission('admin.media.delete',   'Удаление')
    ->addPermission('admin.media.collections.manage', 'Управление коллекциями');
```

## 6. Конфиг

`config/admin-media.php`:

```php
return [
    'disk'        => env('ADMIN_MEDIA_DISK', 'public'),
    'path_prefix' => 'media',                 // Storage::disk()->path('media/...')

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'image/avif',
        'application/pdf',
        'video/mp4', 'video/webm',
        'audio/mpeg', 'audio/wav',
    ],

    'max_size_mb' => 50,

    'collections' => [
        'default'  => ['label' => 'Общая'],
        'articles' => ['label' => 'Статьи'],
        'avatars'  => ['label' => 'Аватары'],
    ],

    'responsive_sets' => [/* см. выше */],

    'image_processor' => [
        'driver'      => 'auto',              // 'auto' | 'gd' | 'imagick' | 'intervention'
        'strip_exif'  => true,
        'auto_orient' => true,
    ],

    'cleanup' => [
        'orphan_after_days' => 30,            // удалять media без attachable через N дней
    ],
];
```

## 7. Подключение

```bash
composer require dskripchenko/laravel-admin-media
php artisan admin:plugin:install media
php artisan migrate
```

## 8. Зачем sister, а не core

- Сильно увеличивает scope (3 таблицы, image-processing, responsive-варианты, focal-point, regenerate jobs).
- Многим достаточно простого `Storage::put()` через core `Field\FileUpload` + `admin_attachments`.
- Отдельный пакет позволяет менять image-processor / responsive-стратегии / cleanup без затрагивания core.

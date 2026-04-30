# File Uploads

## Простой FileUpload field

```php
FileUpload::make('attachment')
    ->accept(['application/pdf', '.docx'])
    ->maxSize(2048)            // 2 MB
    ->disk('s3');              // override config('admin.uploads.disk')
```

Backend получает `path` (storage-relative). Чтобы получить публичный URL:
`Storage::disk($field['disk'])->url($field['path'])`.

## Image upload + crop

```php
ImageCropper::make('avatar')
    ->image()                  // accept = image/*
    ->aspectRatio(1)           // квадрат
    ->minCrop(200, 200)
    ->outputSize(400, 400)
    ->quality(0.9);
```

SPA рисует UI обрезки на клиенте перед загрузкой. Backend получает
уже обрезанную картинку.

## Multiple files

```php
FileUpload::make('gallery')
    ->multiple()
    ->maxFiles(20)
    ->image();
```

State хранится как `list<{path, url, name, size, mime}>`. Eloquent
side: `protected $casts = ['gallery' => 'array']`.

## Wysiwyg с inline-загрузкой картинок

```php
Wysiwyg::make('body')
    ->preset('default')
    ->uploadImages(true);  // POST /api/admin/uploads/image
```

SPA при drop'е/paste'е картинок шлёт их на upload-endpoint и
вставляет полученный URL в Tiptap.

## Конфигурация

```php
// config/admin.php
'uploads' => [
    'disk' => env('ADMIN_UPLOADS_DISK', 'local'),
    'directory' => 'uploads',
    'max_kilobytes' => 51200,           // 50 MB для generic upload
    'max_kilobytes_image' => 10240,     // 10 MB для images
],
```

## Custom upload-handler

Если нужен post-processing (resize, watermark, virus scan), создайте
свой controller и переопределите endpoint в SPA через `uploadImages($endpoint)`.

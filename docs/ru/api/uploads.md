# API: Uploads

Контроллер `uploads` (`Dskripchenko\LaravelAdmin\Uploads\UploadController`) — загрузка файлов на диск и отдача их обратно через API. Им пользуются поля `FileField` (`/uploads/upload`, при `image: true` — `/uploads/image`), `ImageCropperField` и `WysiwygField` (`/uploads/image`).

Загрузка одним запросом: chunked-загрузки, записи о файлах в БД, привязки к моделям и удаления через API в ядре нет. Контроллер только кладёт файл на диск и возвращает его координаты; что с ними делать, решает поле или хост.

> Медиа-библиотека — отдельный пакет `dskripchenko/laravel-admin-media` со своими маршрутами, см. [../sister-packs/media.md](../sister-packs/media.md).

URL: `/api/admin/uploads/{action}`. Все actions требуют аутентификации (`AdminAuth`, см. [system.md](system.md)); отдельных прав нет.

---

## Регистрация в `AdminApi::getMethods()`

```php
'uploads' => [
    'controller' => \Dskripchenko\LaravelAdmin\Uploads\UploadController::class,
    'actions' => [
        'upload' => ['method' => ['post']],
        'image'  => ['method' => ['post']],
        'serve'  => ['method' => ['get']],
    ],
],
```

---

## Конфигурация — `admin.uploads`

| Ключ | По умолчанию | Назначение |
|---|---|---|
| `disk` | `env('ADMIN_UPLOADS_DISK', 'local')` | диск для загрузок |
| `directory` | `uploads` | каталог на диске; картинки — в `{directory}/images` |
| `max_kilobytes` | `51200` (50 МБ) | лимит для `upload` |
| `max_kilobytes_image` | `10240` (10 МБ) | лимит для `image` |
| `servable_disks` | `[ADMIN_UPLOADS_DISK, 'public']` | диски, которые можно отдавать через `serve` |

Имя файла на диске генерирует Laravel (`UploadedFile::store()`); исходное имя возвращается в ответе.

---

## Ответ загрузки

`upload` и `image` возвращают одинаковую форму (`{UploadResponse}`):

```json
{
  "success": true,
  "payload": {
    "disk": "local",
    "path": "uploads/images/aB3x....png",
    "url": "/api/admin/uploads/serve?disk=local&path=uploads%2Fimages%2FaB3x....png",
    "name": "photo.png",
    "size": 48213,
    "mime": "image/png"
  }
}
```

`url` всегда ведёт на `uploads/serve` (строится `UploadController::serveUrl($disk, $path)` от API-пути панели), поэтому файл доступен и с приватного диска.

---

## `uploads.upload`

`POST /api/admin/uploads/upload`, `multipart/form-data`.

| Параметр | Правила |
|---|---|
| `file` | `required`, `file`, `max:{admin.uploads.max_kilobytes}` |

Файл любого типа сохраняется в `{directory}` на диске `admin.uploads.disk`.

Ошибки: `422 validation` (`messages.file`).

## `uploads.image`

`POST /api/admin/uploads/image`, `multipart/form-data`.

| Параметр | Правила |
|---|---|
| `file` | `required`, `file`, `image`, `max:{admin.uploads.max_kilobytes_image}` |

Только изображения; сохраняются в `{directory}/images`.

Ошибки: `422 validation` (`messages.file`).

## `uploads.serve`

`GET /api/admin/uploads/serve?disk=...&path=...`

| Параметр | Описание |
|---|---|
| `disk` | имя диска; должен быть в `admin.uploads.servable_disks` |
| `path` | путь файла на диске |

Ответ `200` — сам файл (`Storage::disk($disk)->response($path)`), не JSON-конверт.

Ошибки:

| HTTP | errorKey | Условие |
|---|---|---|
| 422 | `validation` | не передан `disk` или `path` |
| 422 | `forbidden_disk` | диска нет в `admin.uploads.servable_disks` |
| 404 | `not_found` | файла нет на диске |

Доступ — как у остальных actions панели: любой вошедший пользователь панели может получить любой файл с разрешённых дисков, зная его путь. Не добавляйте в `servable_disks` диски с данными, которые не должны быть видны всем пользователям панели.

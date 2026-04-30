# API: Uploads

Загрузка файлов: одиночная, chunked для больших, аватары, attachments.

> Расширенная медиа-библиотека (коллекции/теги/focal-point/responsive-варианты) — sister-pack `laravel-admin-media`. Здесь — базовый core upload, который пишет в `admin_attachments`.

URL: `api/admin/uploads/{action}`.

---

## UploadsController

### Регистрация

```php
'uploads' => [
    'controller' => UploadsController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'upload'         => ['method' => ['post']],
        'show'           => ['method' => ['get']],
        'delete'         => ['method' => ['post']],
        'chunkedStart'   => ['method' => ['post']],
        'chunkedChunk'   => ['method' => ['post']],
        'chunkedFinish'  => ['method' => ['post']],
        'chunkedCancel'  => ['method' => ['post']],
    ],
],
```

---

## Действия

### `uploads.upload`

```php
/**
 * Одиночная загрузка файла. Запрос — multipart/form-data.
 *
 * @header string $Content-Type multipart/form-data.
 *
 * @input file   $file Сам файл.
 * @input string ?$disk Имя storage-disk (default из config/admin.php).
 * @input string ?$collection Группа (например 'articles').
 * @input string ?$attachable_type Morph-type для немедленной привязки.
 * @input mixed  ?$attachable_id Morph-id.
 * @input string ?$meta JSON-строка с произвольными метаданными.
 *
 * @output object $payload
 * @output object $payload.upload
 * @output string $payload.upload.id UUID.
 * @output string $payload.upload.url Публичный URL (если disk=public).
 * @output string ?$payload.upload.preview_url Thumbnail для image, иначе null.
 * @output string $payload.upload.mime
 * @output integer $payload.upload.size В байтах.
 * @output string $payload.upload.original_name
 * @output integer ?$payload.upload.width Только для image.
 * @output integer ?$payload.upload.height
 * @output string ?$payload.upload.collection
 * @output string(date-time) $payload.upload.created_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {UploadCreatedResponse}
 * @response 413 {PayloadTooLargeResponse}
 * @response 415 {UnsupportedMediaTypeResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} admin.uploads.create.
 */
public function upload(Request $request): JsonResponse;
```

### `uploads.show`

```php
/**
 * Получить metadata одного upload'а.
 *
 * @input string(uuid) $id
 *
 * @output object $payload
 * @output object $payload.upload Тот же формат, что в upload action.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {UploadShowResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function show(Request $request): JsonResponse;
```

### `uploads.delete`

```php
/**
 * Удалить файл (из storage и БД). Если upload прикреплён к записи через
 * attachable_*, удаляется только связь, но не сама запись.
 *
 * @input string(uuid) $id
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Не владелец и нет admin.uploads.delete_any.
 */
public function delete(Request $request): JsonResponse;
```

---

## Chunked upload (для файлов > 10MB)

### `uploads.chunkedStart`

```php
/**
 * Начать chunked-сессию. Возвращает upload_id для последующих chunks.
 *
 * @input string $filename
 * @input integer $size В байтах.
 * @input string $mime
 * @input integer $total_chunks Сколько кусков будет.
 * @input integer $chunk_size В байтах (typically 5MB).
 * @input string ?$collection
 * @input object ?$meta
 *
 * @output object $payload
 * @output string $payload.upload_id UUID сессии.
 * @output string $payload.chunk_endpoint URL для chunkedChunk.
 * @output string $payload.finish_endpoint URL для chunkedFinish.
 * @output string(date-time) $payload.expires_at TTL сессии.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {ChunkedStartResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function chunkedStart(Request $request): JsonResponse;
```

### `uploads.chunkedChunk`

```php
/**
 * Загрузить один chunk в начатую сессию. Запрос — multipart/form-data.
 *
 * @header string $Content-Type multipart/form-data.
 *
 * @input string(uuid) $upload_id
 * @input file $chunk
 * @input integer $index 0-based.
 * @input string $checksum SHA256 chunk'а.
 *
 * @output object $payload
 * @output integer $payload.received Сколько chunks принято.
 * @output integer $payload.total
 * @output integer ?$payload.next_index null если все приняты.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ChunkAcceptedResponse}
 * @response 422 {ChunkChecksumMismatchResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function chunkedChunk(Request $request): JsonResponse;
```

### `uploads.chunkedFinish`

```php
/**
 * Завершить chunked-сессию: собрать chunks в один файл, валидировать,
 * сохранить в storage и admin_attachments.
 *
 * @input string(uuid) $upload_id
 *
 * @output object $payload
 * @output object $payload.upload Тот же формат, что в upload action.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {UploadCreatedResponse}
 * @response 422 {ValidationErrorResponse} Файл не прошёл валидацию.
 * @response 404 {NotFoundErrorResponse}
 */
public function chunkedFinish(Request $request): JsonResponse;
```

### `uploads.chunkedCancel`

```php
/**
 * Отменить chunked-сессию (удалить уже принятые chunks).
 *
 * @input string(uuid) $upload_id
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 */
public function chunkedCancel(Request $request): JsonResponse;
```

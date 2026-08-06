# API: Exports и Imports

CSV/XLSX/PDF-экспорт + 4-шаговый import wizard. Реализуются как actions того же controller'а, что и Resource (`api/admin/{resource_slug}/{action}`).

> Концепции — ARCHITECTURE.md п.5.21. Конвенции — [conventions.md](conventions.md). Resource CRUD — [resources.md](resources.md).

---

## Export

### `users.export`

```php
/**
 * Запустить экспорт.
 *
 * @input string $format csv|xlsx|pdf.
 * @input string $scope all|filtered|selected.
 * @input array  ?$ids Если scope=selected.
 * @input integer ?$ids[]
 * @input array  ?$filters Если scope=filtered.
 * @input string  $filters[].column
 * @input string  $filters[].operator
 * @input mixed   ?$filters[].value
 * @input array  ?$order
 * @input array  ?$columns Только эти колонки; default — все видимые.
 * @input object ?$options Format-specific опции.
 * @input string ?$options.delimiter CSV.
 * @input boolean ?$options.bom CSV.
 * @input string ?$options.paper a4|a3|letter (PDF).
 * @input string ?$options.orientation portrait|landscape (PDF).
 * @input string ?$options.locale Для дат и форматирования.
 *
 * @output object $payload Всегда delayed.
 * @output object $payload.delayed
 * @output string $payload.delayed.uuid
 * @output string $payload.delayed.status new.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 202 {DelayedResponse}
 * @response 404 {NotFoundErrorResponse} Формат не поддерживается этим Resource.
 * @response 422 {MissingExportDriverResponse} PDF/XLSX driver не установлен.
 * @response 403 {ForbiddenErrorResponse} <resource>.view + <resource>.export.
 */
public function export(Request $request): JsonResponse;
```

**Финальный payload** (после завершения, через `applyAxiosInterceptor` или manual poll):

```json
{
  "success": true,
  "payload": {
    "download_url": "/api/admin/users/exportDownload?uuid=01...",
    "filename": "users-2026-04-30.xlsx",
    "size_bytes": 123456,
    "rows": 1234,
    "expires_at": "2026-05-07T10:00:00Z",
    "message": "Экспортировано записей: 1234"
  }
}
```

`download_url` действителен ограниченное время (default 7 дней).

**MissingExportDriverResponse:**

```json
{
  "success": false,
  "payload": {
    "errorKey": "missing_export_driver",
    "message": "PDF-рендерер не установлен. Установите mpdf/mpdf или dompdf/dompdf",
    "command": "composer require mpdf/mpdf"
  }
}
```

Events: `Admin\Events\ExportStarted` → `ExportCompleted` или `ExportFailed`.

### `users.exportStatus`

```php
/**
 * Альтернатива polling'у через delayed.status — alias на ту же логику,
 * но проверяет принадлежность процесса к текущему resource (защита от
 * cross-resource обращения).
 *
 * @input string(uuid) $uuid
 *
 * @output object $payload DelayedProcessStatus (см. delayed.md).
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {DelayedStatusResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function exportStatus(Request $request): JsonResponse;
```

### `users.exportDownload`

```php
/**
 * Скачать готовый файл экспорта.
 *
 * @input string(uuid) $uuid
 *
 * @output file $payload Файл с Content-Disposition: attachment.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {FileDownloadResponse}
 * @response 404 {NotFoundErrorResponse} Файл удалён или uuid не существует.
 * @response 403 {ForbiddenErrorResponse} Не инициатор и нет admin.systems.exports.download_any.
 */
public function exportDownload(Request $request): JsonResponse;
```

Это специальный case — action возвращает не JSON envelope, а файл с правильными headers (`Content-Disposition`, `Content-Type`). `laravel-api` поддерживает этот режим через специальный `ApiController::file()` helper.

---

## Import (4-шаговый wizard)

Включается через `Resource::importable()`.

### `users.importUpload`

**Шаг 1: Загрузка файла.**

```php
/**
 * Загрузить файл для импорта (CSV/XLSX). Возвращает upload_id и auto-detect
 * метаданных — колонки, sample rows, fuzzy-mapping.
 *
 * @input file $file CSV или XLSX.
 *
 * Альтернативно (если файл уже залит через uploads.upload):
 * @input string(uuid) ?$upload_id
 *
 * @output object $payload
 * @output string $payload.upload_id Токен для следующих шагов.
 * @output array  $payload.columns_detected Имена/индексы колонок из файла.
 * @output array  $payload.sample_rows Первые 5 строк для preview.
 * @output integer $payload.total_rows_estimate
 * @output array  $payload.target_fields Список полей Resource'а.
 * @output string $payload.target_fields[].name
 * @output string $payload.target_fields[].label
 * @output boolean $payload.target_fields[].required
 * @output object $payload.auto_mapping file_column → resource_field, fuzzy-match.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ImportUploadResponse}
 * @response 422 {InvalidImportFileResponse}
 * @response 403 {ForbiddenErrorResponse} <resource>.create + <resource>.import.
 */
public function importUpload(Request $request): JsonResponse;
```

### `users.importPreview`

**Шаг 2-3: Маппинг колонок + preview с валидацией.**

```php
/**
 * Применить mapping и валидировать первые N строк, вернуть preview.
 *
 * @input string(uuid) $upload_id
 * @input object $mapping file_column → resource_field. null = пропустить колонку.
 * @input object $options
 * @input boolean $options.skip_errors
 * @input boolean $options.update_existing
 * @input string  ?$options.update_key Поле для upsert (например 'email').
 * @input boolean $options.skip_first_row
 *
 * @output object $payload
 * @output array  $payload.preview Список ImportPreviewRow.
 * @output integer $payload.preview[].row_number 1-based.
 * @output string  $payload.preview[].status create|update|skip|fail.
 * @output object  $payload.preview[].data Отмаппленные значения.
 * @output object  ?$payload.preview[].errors field → messages[].
 * @output object  $payload.summary
 * @output integer $payload.summary.total
 * @output integer $payload.summary.will_create
 * @output integer $payload.summary.will_update
 * @output integer $payload.summary.will_skip
 * @output integer $payload.summary.will_fail
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ImportPreviewResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 404 {NotFoundErrorResponse} upload_id не существует.
 * @response 403 {ForbiddenErrorResponse}
 */
public function importPreview(Request $request): JsonResponse;
```

### `users.importRun`

**Шаг 4: Запуск.**

```php
/**
 * Запустить импорт в delayed-process.
 *
 * @input string(uuid) $upload_id
 * @input object $mapping
 * @input object $options (те же что у importPreview).
 *
 * @output object $payload Всегда delayed.
 * @output object $payload.delayed
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 202 {DelayedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function importRun(Request $request): JsonResponse;
```

**Финальный payload** (через interceptor):

```json
{
  "success": true,
  "payload": {
    "imported": 1200,
    "updated": 30,
    "skipped": 4,
    "failed": 0,
    "errors_csv_url": null,
    "duration_seconds": 18.4,
    "message": "Импорт завершён успешно"
  }
}
```

Events: `Admin\Events\ImportStarted` → `ImportProgressUpdated` → `ImportCompleted`/`ImportFailed`.

### `users.importCancel`

```php
/**
 * Отменить импорт. До запуска через importRun — удаляет upload-сессию.
 * После — отменяет delayed-process (через delayed.cancel под капотом).
 *
 * @input string(uuid) $upload_id
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse}
 */
public function importCancel(Request $request): JsonResponse;
```

### `users.importErrors`

```php
/**
 * Скачать CSV с ошибочными строками.
 *
 * @input string(uuid) $upload_id
 *
 * @output file $payload CSV.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {FileDownloadResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 403 {ForbiddenErrorResponse} Только инициатор импорта.
 */
public function importErrors(Request $request): JsonResponse;
```

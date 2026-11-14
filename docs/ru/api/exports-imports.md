# API: Exports и Imports

Экспорт списка — action `export` контроллера ресурса (`/api/admin/{slug}/export`), файл отдаётся сразу потоком. Импорт — отдельный контроллер `import` (`/api/admin/import/{action}`), ресурс указывается параметром.

> Конвенции — [conventions.md](conventions.md). Resource CRUD — [resources.md](resources.md).

---

## Export

### `{slug}.export` (GET, POST)

```php
/**
 * Потоковый экспорт списка в любой зарегистрированный формат.
 *
 * Принимает фильтры, `q`, колонки и формат — один из форматов ExporterRegistry, по умолчанию csv.
 *
 * @input [operationSchema]
 *
 * @security AdminSession
 *
 * @response 200 {FileDownloadResponse}
 * @response 422 {ValidationErrorResponse} Формат не поддерживается.
 */
public function export(Request $request): StreamedResponse|JsonResponse;
```

Регистрируется для каждого ресурса, требует `<base>.view` (`admin.{slug}.view`).

| Параметр | Описание |
|---|---|
| `format` | формат из `ExporterRegistry`, по умолчанию `csv` |
| `filters` | те же значения фильтров ресурса, что у `search` |
| `q` | строка поиска по `searchableFields()`, как у `search` |
| `columns` | имена колонок для выгрузки; если не переданы — все колонки, кроме скрытых по умолчанию (`defaultHidden`) |

Ответ — сам файл (`StreamedResponse` с `Content-Disposition: attachment`), без JSON-конверта. Имя файла — `{slug}-{Y-m-d-His}.{расширение}`. Строки — `Model::toArray()` каждой записи, заголовки — подписи колонок. Записи читаются курсором, поэтому большие выгрузки не держатся в памяти целиком.

Незарегистрированный формат — `422`:

```json
{
  "success": false,
  "payload": {
    "errorKey": "unsupported_format",
    "message": "Format `pdf` is not registered. Available: csv, json"
  }
}
```

### Форматы

| Формат | Класс | Когда доступен |
|---|---|---|
| `csv` | `CsvExporter` | всегда; настройки — `admin.exports.csv` (`delimiter`, `enclosure`, `bom`) |
| `json` | `JsonExporter` | всегда; при `admin.exports.json.lines = true` — NDJSON (файл `.jsonl`), иначе JSON-массив |
| `xlsx` | `XlsxExporter` | если установлен `openspout/openspout` |
| `pdf` | `PdfExporter` | если установлен `mpdf/mpdf` или `dompdf/dompdf`; драйвер — `admin.exports.pdf.driver` (`mpdf` по умолчанию, при его отсутствии берётся установленный) |

Свой формат добавляется реализацией `Export\Exporter` и регистрацией в `ExporterRegistry` (`add()`).

`Resource::exportable()` (по умолчанию `['csv']`) — список форматов, который попадает в `features.exportable` метаданных и определяет, какие кнопки экспорта показывает SPA; пустой список скрывает экспорт. Сам эндпоинт принимает любой формат из `ExporterRegistry`.

---

## Import

Четырёхшаговый мастер. Контроллер `import` регистрируется статически в `AdminApi::getMethods()`:

```php
'import' => [
    'controller' => ImportController::class,
    'actions' => [
        'upload'  => ['method' => ['post']],
        'preview' => ['method' => ['post']],
        'start'   => ['method' => ['post']],
        'status'  => ['method' => ['get']],
    ],
],
```

Доступ (с 1.75.1). Каждый шаг — `upload`, `preview`, `start` — проверяет:

- ресурс разрешает импорт: `Resource::importable()` возвращает `true` (по умолчанию `false`), иначе `403`, `errorKey: import_disabled`;
- у пользователя есть право на создание записей ресурса — `<base>.create` (`admin.{slug}.create`), то же, что требует `{slug}/create`; проверка идёт через `hasAccess()` модели пользователя, поэтому мост прав проекта (разделы «только чтение», запрещённые действия) действует и здесь. Нет права — `403`, `errorKey: forbidden`, `payload.permission`.

`Resource::importable()` также попадает в `features.importable` метаданных и определяет, показывает ли SPA мастер импорта для ресурса.

Поддерживаемые файлы: `csv`, `tsv`, `txt` (разделитель определяется автоматически по первой строке, BOM пропускается) и `xlsx` (только первый лист; нужен `openspout/openspout`). Первая строка файла — заголовки.

Диск для файлов — только `admin.imports.disk` (по умолчанию `local`), каталог — `admin.imports.directory` (по умолчанию `imports`). Каждый пользователь загружает в свою папку `{directory}/{хеш пользователя}`; `preview` и `start` читают файл только из папки текущего пользователя — чужую загрузку или любой другой файл диска по пути не прочитать. Пути с `..`, `.`, обратными слэшами или ведущим `/` отклоняются.

### `import.upload` (POST, multipart)

**Шаг 1: загрузка файла.**

| Параметр | Описание |
|---|---|
| `file` | файл, не больше `admin.uploads.max_kilobytes` (по умолчанию 51200 КБ) |
| `resource` | slug ресурса, в который идёт импорт |

Файл сохраняется в папку пользователя (см. выше). Ответ `200 {ImportUploadResponse}`: `{disk, path}` — `path` передаётся в `preview` и `start` как есть. Незарегистрированный ресурс — `422`, `errorKey: unknown_resource`.

### `import.preview` (POST)

**Шаг 2: заголовки, образец и автоматическое сопоставление.**

| Параметр | Описание |
|---|---|
| `resource` | slug ресурса |
| `path` | путь из `upload` |
| `disk` | необязателен; если передан, должен совпадать с `admin.imports.disk`, иначе `422`, `errorKey: forbidden_disk` |

Ответ `200 {ImportPreviewResponse}`:

```json
{
  "success": true,
  "payload": {
    "headers": ["Name", "E-mail"],
    "sample": [ { "Name": "Ivan", "E-mail": "ivan@example.com" } ],
    "total": 1200,
    "format": "csv",
    "auto_mapping": { "Name": "name" }
  }
}
```

- `sample` — первые 20 строк.
- `total` — число строк данных (без заголовков); для пустого XLSX — `null`.
- `auto_mapping` — `{заголовок файла: имя поля}` по полям формы создания ресурса: точное совпадение, без учёта регистра, по подписи поля, по snake_case заголовка. Несопоставленные заголовки в карту не попадают и при импорте пропускаются.

### `import.start` (POST)

**Шаги 3–4: подтверждённое сопоставление и запуск.**

| Параметр | Описание |
|---|---|
| `resource` | slug ресурса |
| `path` | путь из `upload` |
| `mapping` | `{заголовок файла: имя поля}`; имя поля — только поле формы создания ресурса (`fields()` с `appliesTo('create')`), пустое значение пропускает колонку |
| `disk` | необязателен; как у `preview` |

Создаёт запись `ImportProcess` (таблица `admin_import_processes`) и сразу выполняет импорт синхронно. Каждая строка проходит `validationRules('create')` ресурса, и в запись попадают только проверенные поля — тем же путём, что у `{slug}/create`: очистка HTML у `Wysiwyg`, переводимые поля, `fillModel()` ресурса (`RecordWriter::create()`), каждая строка в своей транзакции. Колонки модели вне формы создания не записываются. Строка с ошибкой не прерывает импорт — она попадает в `errors`.

Ответ `200 {ImportStartResponse}`: `{process}` — состояние после завершения (формат — как у `status`).

| HTTP | `errorKey` | Когда |
|---|---|---|
| 403 | `import_disabled` | `importable()` ресурса — `false` |
| 403 | `forbidden` | нет права `<base>.create` |
| 422 | `unknown_resource` | ресурс не зарегистрирован |
| 422 | `forbidden_disk` | `disk` отличается от `admin.imports.disk` |
| 422 | `file_missing` | файла нет, или путь не из папки загрузок текущего пользователя |
| 422 | `invalid_mapping` | колонка сопоставлена не с полем формы создания; `payload.fields` — такие имена |

### `import.status` (GET)

`id` процесса. Ответ `200 {ImportStatusResponse}`:

```json
{
  "success": true,
  "payload": {
    "process": {
      "id": 7,
      "resource_slug": "users",
      "status": "completed",
      "processed_count": 1200,
      "created_count": 1196,
      "updated_count": 0,
      "error_count": 4,
      "errors": [ { "row": 15, "error": "The email field must be a valid email address." } ],
      "started_at": "2026-04-30T10:00:00+00:00",
      "completed_at": "2026-04-30T10:00:18+00:00"
    }
  }
}
```

`status` — `pending`, `running`, `completed` или `failed`. `row` в `errors` — номер строки файла с учётом строки заголовков; ошибка всего импорта записывается с `row: 0` и статусом `failed`. Процесс виден тому, кто его запустил, и пользователям с правом `<base>.create` его ресурса; остальным, как и для несуществующего процесса, — `404`, `errorKey: not_found`.

Импорт выполняется синхронно в запросе `start`. Для фонового выполнения зарегистрируйте свой обработчик в `AllowlistRegistrar` и запускайте его через `delayed/run` — см. [actions.md](actions.md#асинхронные-действия-asyncaction).

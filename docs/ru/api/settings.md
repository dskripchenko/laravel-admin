# API: Settings

Singleton-Resource'ы (одна запись, нет list/create/delete). Каждый SettingsResource — отдельный controller со slug = `Resource::slug()`.

> Конвенции — [conventions.md](conventions.md). Базовая иерархия — `Resource\SettingsResource extends Resource` (см. ARCHITECTURE.md п.5.20). Регистрация — [registration.md](registration.md).

URL: `api/admin/{settings_slug}/{action}`. Например, `api/admin/general-settings/show`.

---

## CompiledSettingsController

### Регистрация (динамически)

```php
'general-settings' => [
    'controller' => CompiledSettingsController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'show'   => ['method' => ['get']],
        'update' => ['method' => ['post']],
        'meta'   => ['method' => ['get']],
    ],
],
```

---

## Действия

### `general-settings.meta`

```php
/**
 * Получить метаданные SettingsResource (поля, layout, валидация).
 *
 * @output object $payload
 * @output array  $payload.fields FieldSchema.
 * @output array  $payload.layout LayoutSchema (обычно Layout::tabs со множеством Rows).
 * @output object $payload.permissions { update: boolean }.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SettingsMetaResponse}
 * @response 403 {ForbiddenErrorResponse} <settings>.view.
 */
public function meta(Request $request): JsonResponse;
```

### `general-settings.show`

```php
/**
 * Получить текущие настройки.
 *
 * @header string ?$If-None-Match
 *
 * @output object $payload
 * @output object $payload.state Dot-notation ключи и значения.
 * @output array  $payload.layout LayoutSchema (обычно дублирует meta для cache-friendly load).
 * @output array  $payload.fields FieldSchema.
 * @output object $payload.permissions { update: boolean }.
 * @output string $payload.etag
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SettingsShowResponse}
 * @response 304 {NotModifiedResponse}
 * @response 403 {ForbiddenErrorResponse} <settings>.view.
 */
public function show(Request $request): JsonResponse;
```

### `general-settings.update`

```php
/**
 * Сохранить настройки.
 * Конкретный набор @input определяется SettingsResource::fields().
 * Пример ниже — для GeneralSettings с полями site_name, logo, primary_color.
 *
 * @input string ?$site_name
 * @input string(uuid) ?$logo_id ID upload'а из uploads.upload (null = удалить).
 * @input string ?$primary_color HEX-цвет (#RRGGBB).
 *
 * @header string ?$If-Match Etag для optimistic concurrency.
 *
 * @output object $payload
 * @output object $payload.state Полное состояние после обновления.
 * @output string $payload.etag Новый.
 * @output string $payload.message
 * @output array  $payload.affected_keys Какие ключи реально изменились.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SettingsUpdateResponse}
 * @response 409 {ConflictResponse} If-Match не совпал.
 * @response 422 {ValidationErrorResponse}
 * @response 403 {ForbiddenErrorResponse} <settings>.update.
 */
public function update(Request $request): JsonResponse;
```

Events: `Admin\Events\SettingsUpdated` (audit с list of affected_keys и diff old/new).

---

## Multi-driver storage

`SettingsStorage` имеет два встроенных driver'а (см. ARCHITECTURE.md п.5.20):

- **Eloquent** — `SettingsStorage::eloquent(Model::class)`. Вся форма мапится на одну запись модели.
- **Key-Value** — `SettingsStorage::keyValue(table: 'admin_settings', group: 'general')`. Каждое поле — отдельная строка.

API одинаковый. Driver выбран SettingsResource'ом, клиент об этом не знает.

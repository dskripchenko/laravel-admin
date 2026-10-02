# API: Settings

Группы настроек — наследники `Settings\SettingsResource`. Это не Resource над моделью: записей нет, вся группа — одна логическая «запись», набор пар ключ → значение. Списка, создания и удаления нет.

> Конвенции — [conventions.md](conventions.md). Регистрация — [registration.md](registration.md).

URL: `/api/admin/settings_{slug}/{action}`. Например, для `BrandSettings` — `/api/admin/settings_brand/read`.

---

## Регистрация

Группа регистрируется в `SettingsRegistry` (`add()` / `addMany()`, с указанием панели). `SettingsCompiler` (`src/Settings/SettingsCompiler.php`) добавляет для каждой группы ключ контроллера `settings_{slug}` с общим контроллером `SettingsController`:

```php
'settings_brand' => [
    'controller' => SettingsController::class,
    'actions' => [
        'meta'   => ['method' => ['get'],  'middleware' => [AdminAccess::class.':admin.settings.brand.view']],
        'read'   => ['method' => ['get'],  'middleware' => [AdminAccess::class.':admin.settings.brand.view']],
        'update' => ['method' => ['post'], 'middleware' => [AdminAccess::class.':admin.settings.brand.update']],
    ],
],
```

- **Slug** — имя класса без суффиксов `Settings` и `Resource` в kebab-case: `BrandSettings` → `brand`, `MailServerSettings` → `mail-server`.
- **База прав** — `SettingsResource::permission()`, по умолчанию `admin.settings.{slug}`. `meta` и `read` требуют `<base>.view`, `update` — `<base>.update`. Нет права — `403`, `errorKey: forbidden`.

---

## `settings_{slug}.meta` (GET)

```php
/**
 * Метаданные группы: поля и права.
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {SettingsMetaResponse}
 */
public function meta(): JsonResponse;
```

```json
{
  "success": true,
  "payload": {
    "kind": "settings",
    "slug": "brand",
    "label": "Brand",
    "permissions": { "view": "admin.settings.brand.view", "update": "admin.settings.brand.update" },
    "fields": [ { "name": "site_name" } ]
  }
}
```

`permissions` — имена прав, а не флаги доступа. Та же структура попадает в `settings[]` манифеста.

## `settings_{slug}.read` (GET)

```php
/**
 * Текущие значения: сохранённое поверх значений по умолчанию.
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {SettingsReadResponse}
 */
public function read(): JsonResponse;
```

Ответ — `{values: {key: value}}`. Значения по умолчанию берутся из `getDefaultValue()` полей (`SettingsResource::defaults()`), поверх них — то, что лежит в хранилище для группы.

## `settings_{slug}.update` (POST)

```php
/**
 * Сохраняет значения через SettingsResource::write().
 *
 * @input object $values
 * @input [operationSchema]
 *
 * @output object $payload
 *
 * @security AdminSession
 *
 * @response 200 {SettingsUpdatedResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function update(Request $request): JsonResponse;
```

```http
POST /api/admin/settings_brand/update
{ "values": { "site_name": "Acme", "primary_color": "#0055ff" } }
```

- `values` проверяется `SettingsResource::validationRules()` — правилами полей для контекста `update`. Ошибки — `422`.
- Сохранение — слияние: переданные ключи записываются, остальные ключи группы не трогаются.
- Ответ — `{values, message: "Saved"}`, где `values` — полный набор после сохранения (как в `read`).

---

## Хранилище

Значения читает и пишет `Settings\Storage\SettingsStorage`. По умолчанию к нему привязан `KeyValueSettingsStorage`: таблица `admin_settings`, одна строка на ключ, `group` = slug группы, значение хранится в JSON. Чтобы хранить настройки иначе (например, в типизированной модели), привяжите в контейнере свою реализацию `SettingsStorage` — API от этого не меняется.

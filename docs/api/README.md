# API contracts

Спецификации всех HTTP-actions admin-API. Все actions реализованы поверх `dskripchenko/laravel-api`. URL имеет жёсткий паттерн `{api_path}/{controller}/{action}` (default: `/api/admin/{controller}/{action}`), а каждый метод обязан иметь полный docblock с тегами `@input`/`@output`/`@header`/`@security`/`@response`.

## URL-паттерн (важно!)

```
{admin.api_path}/{controller}/{action}

Default: /api/admin/{controller}/{action}
```

- **API живёт отдельно от SPA-shell.** SPA — на `/admin/*` (под `admin.path`). API — на `/api/admin/*` (под `admin.api_path`). Они **не вложены** друг в друга.
- **Никаких path-параметров** кроме `{controller}` и `{action}`.
- Все параметры (id, фильтры, реляции) идут через **Request body** (для POST/...) или **query** (для GET).
- HTTP-метод задаётся в `getMethods()` явно: `'method' => ['post']`.

## Структура документа

| Файл | Содержимое |
|---|---|
| [conventions.md](conventions.md) | Общие конвенции: envelope, заголовки, ошибки, пагинация, фильтры, etag, idempotency, throttling, обязательные требования к docblock'ам |
| [registration.md](registration.md) | Структура `AdminApi::getMethods()`, динамическая регистрация Resource/Screen-контроллеров, schema-templates через `getOpenApiTemplates()`, security schemes, middleware-каскад |
| [schemas.md](schemas.md) | Полный реестр всех ~140 named-templates (`{XxxResponse}`) с описанием структуры. Реализация — в `src/Http/AdminApi.php` + traits `src/Http/Schemas/`. |
| [system.md](system.md) | controller `system`: bootstrap, manifest, me, menu, locales, permissions, plugins, notifications, audit |
| [auth.md](auth.md) | controller `auth`: login, logout, password-reset, email-verify, 2FA-challenge, impersonation |
| [profile.md](profile.md) | controller `profile`: профиль, смена пароля, 2FA-setup, recovery codes, API-токены |
| [resources.md](resources.md) | per-Resource controllers (slug = Resource::slug()): meta, search, read, create, update, delete, restore, forceDelete, replicate, inlineEdit, view, audit, reactiveField, reorder, relations*, views*, preferences* |
| [actions.md](actions.md) | bulk- и single-record actions, parameters, async actions через delayed-process |
| [screens.md](screens.md) | per-Screen controllers: state, runMethod, async |
| [settings.md](settings.md) | per-SettingsResource controllers: meta, show, update |
| [dashboards.md](dashboards.md) | controller `dashboards`: list, show, widgetData, saveLayout, resetLayout, duplicate |
| [exports-imports.md](exports-imports.md) | actions Resource-controller'а: export, exportStatus, exportDownload, importUpload, importPreview, importRun, importCancel, importErrors |
| [uploads.md](uploads.md) | controller `uploads`: upload, show, delete, chunkedStart, chunkedChunk, chunkedFinish, chunkedCancel |
| [delayed.md](delayed.md) | controller `delayed`: status, cancel, list |
| [search.md](search.md) | controller `search` (sister-pack): global |
| [health.md](health.md) | controller `health` (sister-pack): summary, checks, run, history |

## OpenAPI и Scalar UI

Все actions автоматически экспортируются в OpenAPI 3.0 через `dskripchenko/laravel-api` (на основе docblock'ов). Доступно:

- `GET /api/admin/openapi.json` — JSON-спецификация.
- `GET /api/admin/doc` — **Scalar UI** для интерактивного просмотра (lazy-loaded, требует permission `admin.system.api-docs`).
- `php artisan admin:api:client admin` — генерация TypeScript-интерфейсов.
- `php artisan admin:api:postman admin` — Postman Collection.
- `php artisan admin:api:http admin` — `.http`-files.

## Обязательные правила

1. **Все actions** объявлены через `AdminApi::getMethods()` — никаких свободных `Route::post(...)` вне laravel-api.
2. **Каждый action** имеет полный docblock: `@input`/`@output`/`@security`/`@response` минимум.
3. **Все ответы** в конверте `{success: true, payload: ...}` или `{success: false, payload: {errorKey, message, ...}}` через `$this->success()` / `$this->error()`.
4. **Permissions** проверяются либо в middleware (через `AdminAccess`), либо явно через `$this->authorize('...')`.
5. **URL** не содержит path-параметров кроме `{controller}/{action}`.

CI-job `admin:api:lint` проверяет это автоматически.

## Стиль документации

- Тип-аннотации в TypeScript-нотации внутри payload-описаний (для краткости).
- Сами action-сигнатуры — PHP с реальным docblock.
- ISO-8601 для дат (`2026-04-30T10:00:00Z`).
- Идентификаторы записей: `string | number` (зависит от модели).
- UUID процессов: UUIDv7 (см. `delayed-process`).
- `null` означает «значение отсутствует/не применимо», в противоположность отсутствию ключа.

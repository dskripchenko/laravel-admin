---
title: Архитектура
audience: developer
status: stable
locale: ru
translated_from: en/architecture.md
translated_at: 2026-05-08
---

# Архитектура

Высокоуровневое описание дизайна `laravel-admin`. Полное описание
архитектуры с rationale — в `docs/ARCHITECTURE.md` (~1500 строк, RU).

## Цели

1. **Resource-first.** Большинство страниц в админке — CRUD. Объяви
   Eloquent-модель как `Resource` и получи list/create/edit/view
   автоматически.
2. **Композируется за пределы CRUD.** Примитивы `Screen`/`Layout`/
   `Field`/`Action` позволяют делать non-CRUD страницы (формы, отчёты,
   дашборды) через тот же render-pipeline.
3. **JSON-driven SPA.** Один bundle от `@dskripchenko/laravel-admin`,
   гидратируется из manifest'а. Host пишет PHP, SPA рендерит.
4. **Без vendor lock-in.** Editor / charts / file-storage / queue —
   pluggable. Sister-pack'и опциональны.
5. **Multi-tenant ready.** Резолвинг tenant'а на стороне host'а; мы
   даём контракты (`TenantResolver`/`TenantContext`/`TenantScoped`).

## Высокоуровневый flow

```
HTTP request (Laravel)
  └── AdminApiModule (laravel-api)
       └── AdminApi::getMethods()
            ├── system / auth / profile / dashboard / audit / ...
            ├── resources (компилируется per-Resource через ResourceCompiler)
            ├── settings (per-Resource через SettingsCompiler)
            └── screens (per-Screen через ScreenCompiler)
                  └── ScreenController::state / runMethod
                        └── Screen::compile() → {state, layout, command_bar, ...}

SPA bootstrap
  └── createAdminApp(bootstrap)
       ├── createAdminClient (axios)
       ├── manifestStore.load() ← /api/admin/system/manifest
       ├── menuStore.load()     ← /api/admin/system/menu
       └── replaceManifestRoutes ← Vue Router из manifest'а
```

## PHP-слои

| Слой | Что | Файл |
|---|---|---|
| `Admin` | Manager-фасад. Точка входа для `resources/screen/menu/...`. | `src/Admin.php` |
| `Resource` | Обёртка модели: fields/columns/filters/actions. | `src/Resource/Resource.php` |
| `Screen` | Абстрактная страница (`query`/`layout`/`commandBar`). | `src/Screen/Screen.php` |
| `Field` | Дескриптор формы. | `src/Field/*` |
| `Layout` | Renderable-контейнер (Rows/Columns/Tabs/...). | `src/Layout/*` |
| `Action` | Button/Link/Bulk/Modal/Async. | `src/Action/*` |
| `Filter` | Табличный фильтр. | `src/Filter/*` |
| `Widget` | Плитка дашборда. | `src/Widget/*` |
| `MenuNode/MenuRegistry` | Иерархическое дерево sidebar'а. | `src/Menu/*` |
| `Permission` | RBAC (Role/Permission, AdminAccess middleware). | `src/Permission/*` |
| `Audit` | `AuditLog` + trait `Loggable`. | `src/Audit/*` |
| `Settings` | Singleton-страницы конфига. | `src/Settings/*` |
| `Tenancy` | Контракты Resolver/Context. | `src/Tenancy/*` |
| `Plugin` | Интерфейс `AdminPlugin`, `PluginRegistry`. | `src/Plugin/*` |
| `Theme/I18n` | ThemeManager, LocaleResolver. | `src/Theme/*`, `src/I18n/*` |
| `Http/AdminApi` | Маппит всё выше в `getMethods()` для laravel-api. | `src/Http/AdminApi.php` |

## Frontend-слои

| Слой | Что | Путь |
|---|---|---|
| `createAdminApp` | Точка входа: client, stores, router, registries, mount. | `resources/ts/createAdminApp.ts` |
| Stores (Pinia) | auth/manifest/menu/theme/locale/notifications/resourceIndex/resourceForm/screen/dashboard. | `resources/ts/stores/*` |
| Router | `buildRoutesFromManifest` + auth-guard + title-guard. | `resources/ts/router/*` |
| Render | `FieldRenderer`, `LayoutRenderer`, `WidgetRenderer`, `provideFormState`. | `resources/ts/components/render/*` |
| Pages | `HomePage`, `ResourceIndexPage`, `ResourceFormPage`, `ResourceViewPage`, `ScreenPage`, `DashboardPage`, ... | `resources/ts/components/*` |
| Shell | `AdminApp`, `AdminTopBar`, `AdminSidebar`, `AdminSidebarNode`, `BrandLogo`, `NotificationsDrawer`. | `resources/ts/components/shell/*` |

## Ключевые контракты

### Manifest

Single source of truth для SPA, отдаётся `/api/admin/system/manifest`:

```json
{
  "version": "sha256-...",
  "locale": "ru",
  "resources": [{ "slug": "articles", "label": "Статьи", "fields": [...], ... }],
  "screens":   [{ "slug": "contact", "name": "Связаться", "permission": null }],
  "settings":  [{ "slug": "brand", "fields": [...] }],
  "dashboards":[{ "slug": "content", "label": "Аналитика", "widgets": [...] }],
  "plugins":   [...],
  "permissions": []
}
```

Кэшируется через ETag (`If-None-Match` / `304`).

### Screen::compile()

Универсальный payload для любого Screen'а:

```json
{
  "state": { "form_field": "value" },
  "name": "Связаться",
  "description": "Свяжитесь с командой",
  "layout": [ { "type": "rows", "children": [...] } ],
  "command_bar": [ { "kind": "action", "type": "button", "name": "send" } ],
  "permissions": [],
  "etag": "0123abcd..."
}
```

### Action dispatch

Действия дёргают controller-method. Стандартный payload:

```json
{ "method": "send", "payload": { "form_field": "value" } }
```

`Resource` actions идут в `ResourceController::action`; `Screen` — в
`ScreenController::runMethod`. Оба возвращают нормализованный shape:
`message`, `alerts`, `state`, `refresh`, `redirect_url`,
`download_url`.

## Permissions model

- Permission — строка: `admin.{resource}.{action}` (например
  `admin.articles.update`).
- Wildcards: `admin.articles.*`, `*`.
- Roles держат списки permissions (`Role::permissions = ['*']`).
- Users держат roles. `User::hasAccess($p)` транзитивно проверяет.
- Middleware `AdminAccess` гейтит на каждом действии.
- `ResourceCompiler` авто-привязывает правильный `AdminAccess:{p}` к
  каждому сгенерированному route'у.

## Pluggable области

| Область | Default | Как переопределить |
|---|---|---|
| WYSIWYG | `@dskripchenko/wysiwyg` | `registerField('wysiwyg', QuillField)` |
| File storage | `Storage::disk('admin')` | host конфигурирует disks |
| PDF rendering | mPDF (если установлен) | `app->bind(PdfRenderer::class, MyRenderer::class)` |
| Charts | Built-in SVG widgets | `registerWidget('chart', MyChart)` |
| Auth guard | `auth.guard = admin` | config |
| User model | `AdminUser` | host `Authenticatable` |

## См. также

- [Глоссарий](glossary.md)
- [API reference](../en/api-reference.md) (en)
- [Frontend extension](../en/frontend-extension.md) (en)

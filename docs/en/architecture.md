---
title: Architecture
audience: developer
status: stable
locale: en
---

# Architecture

This document describes the high-level design of `laravel-admin`. For
the deep, low-level architecture see also `docs/ARCHITECTURE.md` (RU,
~1500 lines, design rationale).

## Goals

1. **Resource-first.** Most pages in an admin are CRUD. Declare an
   Eloquent model as a `Resource` and get list/create/edit/view for free.
2. **Composable beyond CRUD.** Provide `Screen`/`Layout`/`Field`/`Action`
   primitives so non-CRUD pages (forms, reports, dashboards) reuse the
   same render-pipeline.
3. **JSON-driven SPA.** A single bundle served by `@dskripchenko/laravel-
   admin`, hydrated from a manifest. The host writes PHP, the SPA
   renders. No two admins are wired the same way.
4. **No vendor lock-in.** Editor / charts / file-storage / queue are
   pluggable. Sister-packs are optional.
5. **Multi-tenant ready.** Tenant resolution is host-side. We provide
   contracts (`TenantResolver`/`TenantContext`/`TenantScoped`).

## High-level flow

```
HTTP request (Laravel)
  └── AdminApiModule (laravel-api)
       └── AdminApi::getMethods()
            ├── system / auth / profile / dashboard / audit / ...
            ├── resources (compiled per-Resource via ResourceCompiler)
            ├── settings (compiled per-Resource via SettingsCompiler)
            └── screens (compiled per-Screen via ScreenCompiler)
                  └── ScreenController::state / runMethod
                        └── Screen::compile() → {state, layout, command_bar, ...}

SPA bootstrap
  └── createAdminApp(bootstrap)
       ├── createAdminClient (axios)
       ├── manifestStore.load() ← /api/admin/system/manifest
       ├── menuStore.load()     ← /api/admin/system/menu
       └── replaceManifestRoutes ← Vue Router built from manifest

User navigates to /admin/r/{slug} (Resource list)
  └── ResourceIndexPage
       └── useResourceIndexStore.load() → POST /{slug}/search
            └── Manifest's columns + filters + actions
```

## PHP layers

| Layer | What | File |
|---|---|---|
| `Admin` | Manager facade. Entry point for `resources/screen/menu/...`. | `src/Admin.php` |
| `Resource` | Model wrapper: fields/columns/filters/actions. | `src/Resource/Resource.php` |
| `Screen` | Abstract page (`query`/`layout`/`commandBar`). | `src/Screen/Screen.php` |
| `Field` | Form input descriptor. | `src/Field/*` |
| `Layout` | Renderable container (Rows/Columns/Tabs/...). | `src/Layout/*` |
| `Action` | Button/Link/Bulk/Modal/Async. | `src/Action/*` |
| `Filter` | Table filter. | `src/Filter/*` |
| `Widget` | Dashboard tile. | `src/Widget/*` |
| `MenuNode/MenuRegistry` | Hierarchical sidebar tree. | `src/Menu/*` |
| `Permission` | RBAC (Role/Permission, AdminAccess middleware). | `src/Permission/*` |
| `Audit` | `AuditLog` + `Loggable` trait. | `src/Audit/*` |
| `Settings` | Singleton config screens. | `src/Settings/*` |
| `Tenancy` | Resolver/Context contracts. | `src/Tenancy/*` |
| `Plugin` | `AdminPlugin` interface, `PluginRegistry`. | `src/Plugin/*` |
| `Theme/I18n` | ThemeManager, LocaleResolver. | `src/Theme/*`, `src/I18n/*` |
| `Http/AdminApi` | Maps everything above into `getMethods()` for laravel-api. | `src/Http/AdminApi.php` |

## Frontend layers

| Layer | What | Path |
|---|---|---|
| `createAdminApp` | Entry: client, stores, router, registries, mount. | `resources/ts/createAdminApp.ts` |
| Stores (Pinia) | auth/manifest/menu/theme/locale/notifications/resourceIndex/resourceForm/screen/dashboard. | `resources/ts/stores/*` |
| Router | `buildRoutesFromManifest` + auth-guard + title-guard. | `resources/ts/router/*` |
| Render | `FieldRenderer`, `LayoutRenderer`, `WidgetRenderer`, `provideFormState`. | `resources/ts/components/render/*` |
| Pages | `HomePage`, `ResourceIndexPage`, `ResourceFormPage`, `ResourceViewPage`, `ScreenPage`, `DashboardPage`, `ProfilePage`, `ImportWizardPage`, `FieldGalleryPage`. | `resources/ts/components/*` |
| Shell | `AdminApp`, `AdminTopBar`, `AdminSidebar`, `AdminSidebarNode`, `BrandLogo`, `NotificationsDrawer`. | `resources/ts/components/shell/*` |

## Key contracts

### Manifest

The single source of truth for the SPA, returned by `/api/admin/system/manifest`:

```json
{
  "version": "sha256-...",
  "locale": "en",
  "resources": [{ "slug": "articles", "label": "Articles", "fields": [...], "columns": [...], ... }],
  "screens":   [{ "slug": "contact", "name": "Contact", "permission": null }],
  "settings":  [{ "slug": "brand", "fields": [...], ... }],
  "dashboards":[{ "slug": "content", "label": "Analytics", "widgets": [...] }],
  "plugins":   [...],
  "permissions": []
}
```

Cached via ETag (`If-None-Match` / `304`).

### Screen::compile()

Universal payload for any screen (Generated or custom):

```json
{
  "state": { "form_field": "value", ... },
  "name": "Contact",
  "description": "Reach the team",
  "layout": [ { "type": "rows", "children": [ { "kind": "field", ... } ] } ],
  "command_bar": [ { "kind": "action", "type": "button", "name": "send", ... } ],
  "permissions": [],
  "etag": "0123abcd..."
}
```

### Action dispatch

Actions invoke a controller method. The standard payload:

```json
{ "method": "send", "payload": { "form_field": "value" } }
```

`Resource` actions go to `ResourceController::action`; `Screen` actions
to `ScreenController::runMethod`. Both return a normalized `payload`
shape (`message`, `alerts`, `state`, `refresh`, `redirect_url`,
`download_url`).

## Permissions model

- A permission is a string: `admin.{resource}.{action}` (e.g.
  `admin.articles.update`).
- Wildcards: `admin.articles.*`, `*`.
- Roles hold lists of permissions (`Role::permissions = ['*']`).
- Users hold roles. `User::hasAccess($p)` checks transitively.
- `AdminAccess` middleware enforces it on every action.
- `ResourceCompiler` auto-attaches the right `AdminAccess:{permission}`
  to each generated route.

## Pluggable areas

| Area | Default | How to override |
|---|---|---|
| WYSIWYG | `@dskripchenko/wysiwyg` | `registerField('wysiwyg', QuillField)` |
| File storage | `Storage::disk('admin')` | host configures disks |
| PDF rendering | mPDF (if installed) | `app->bind(PdfRenderer::class, MyRenderer::class)` |
| Charts | Built-in SVG widgets | `registerWidget('chart', MyChart)` |
| Auth guard | `auth.guard = admin` | config |
| User model | `AdminUser` | host `Authenticatable` |
| Locale source | 5-step resolver | host config |

## See also

- [Glossary](glossary.md) — terminology
- [API reference](api-reference.md) — REST endpoints
- [Frontend extension](frontend-extension.md) — host-side custom components

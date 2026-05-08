# dskripchenko/laravel-admin

> 🌐 [English](README.md) · [Русский](README.ru.md) · **Deutsch** · [中文](README.zh.md)

Ein Laravel Admin-Panel-Konstruktor im Stil von Orchid mit einem Vue 3 SPA-Frontend.

[![npm](https://img.shields.io/npm/v/@dskripchenko/laravel-admin?label=%40dskripchenko%2Flaravel-admin)](https://www.npmjs.com/package/@dskripchenko/laravel-admin)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin)](https://packagist.org/packages/dskripchenko/laravel-admin)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin)](LICENSE)

```php
Admin::resources([UserResource::class, ArticleResource::class]);
Admin::screen([ContactScreen::class, SystemStatusScreen::class]);
Admin::menu()->add(
    MenuNode::make('content', 'Inhalte')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::dashboard('analytics'),
    ]),
);
```

## Was ist enthalten

- **CRUD-Pipeline** — deklarieren Sie ein Eloquent-Modell als
  `Resource` und erhalten Sie List/Create/Edit/View-Screens automatisch.
- **Custom Screens** — Nicht-CRUD-Seiten (Formulare, Berichte,
  Dashboards) mit `Admin::screen()`. Verwaltet State, Layout,
  Command-Bar, Validierung, Permissions.
- **Hierarchisches Menü** — fluentes API
  `Admin::menu()->add(MenuNode::...)`, beliebige Tiefe, automatische
  Auflösung von `resource()`/`screen()`/`dashboard()`.
- **30+ Feldtypen** — Input/Number/Select/Combobox/DatePicker/
  ColorPicker/FileUpload/Wysiwyg/Markdown/TranslatableInput/Repeater/
  RelationSelect/Cascader/TreeSelect/Slug/KeyValue/TagsInput/...
- **15+ Layouts** — Rows/Columns/Tabs/Wizard+Step/Block/Modal/Drawer/
  Wrapper/Infolist/Dashboard/Accordion/View/...
- **Tabellen** — sortierbare Spalten, Presets, Filter, Inline-Edit,
  Summary, Saved Views, Group-by, Polling, Export (CSV/XLSX/PDF).
- **Dashboard** — 8 Widget-Typen (Stats/Chart/RecentList/Markdown/
  Iframe/Table/Heatmap/Gauge), benutzerspezifische Layout-Overrides,
  Drag/Resize, Polling.
- **Auth & RBAC** — Multi-Guard, AdminUser, Roles, 2FA TOTP, Profile,
  Impersonation, Password-Reset, E-Mail-Verifikation.
- **Audit** — Append-Only-Log von Admin-Aktionen
  (`AuditLog` + `Loggable`-Trait).
- **Settings** — Singleton-Konfigurationsscreens.
- **Notifications** — Bell-Badge + Drawer (Database-Notifications).
- **API-Tokens** — Sanctum-Integration im Profil (optional).
- **Theming** — Light/Dark + Benutzerpräferenz, `@dskripchenko/ui`
  Design-Tokens.
- **i18n** — Locale-Resolver (5-Stufen-Priorität),
  `TranslatableField`-Bridge für
  `dskripchenko/laravel-translatable`.
- **Mandantenfähigkeit** — `TenantResolver` / `TenantContext` /
  `TenantScoped`-Trait. Strategie ist host-seitig; wir liefern den
  Vertrag.
- **Plugins** — `AdminPlugin`-Interface; Sister-Packs nutzen denselben
  Hook.
- **Testing** — `ResourceTestCase`, `ScreenTestCase`,
  `ActsAsAdmin`-Trait.
- **OpenAPI 3.0** — generiert aus Docblock-Tags `@input`/`@output`.

## Installation

```bash
composer require dskripchenko/laravel-admin
php artisan vendor:publish --tag=admin-config
php artisan migrate
```

```js
// resources/js/admin.js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui
npm run build
```

Öffnen Sie `/admin/login`. Siehe
[getting-started.md](docs/de/getting-started.md) für die erste Resource.

## Dokumentation

- [Erste Schritte](docs/de/getting-started.md)
- [Architektur](docs/de/architecture.md)
- Konzepte: [Resources](docs/de/concepts/resources.md) ·
  [Screens](docs/de/concepts/screens.md) ·
  [Widgets & Dashboards](docs/de/concepts/widgets-and-dashboards.md) ·
  [Menü](docs/de/concepts/menu.md) ·
  [Actions](docs/en/concepts/actions.md) (en) ·
  [Permissions](docs/en/concepts/permissions.md) (en) ·
  [i18n](docs/en/concepts/i18n.md) (en) ·
  [Mandanten](docs/en/concepts/tenancy.md) (en)
- [Felder-Referenz](docs/en/fields-reference.md) (en)
- [Layouts-Referenz](docs/en/layouts-reference.md) (en)
- [API-Referenz](docs/en/api-reference.md) (en)
- [Frontend-Erweiterung](docs/en/frontend-extension.md) (en)
- [Testen](docs/en/testing.md) (en)
- [Migration Guide](docs/en/migration-guide.md) (en)
- [Glossar](docs/de/glossary.md)

## Stack

- **PHP** ^8.5
- **Laravel** ^12
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Bundle** — `@dskripchenko/laravel-admin` ~62 KB gz (esm + cjs)
- **Kein Vendor-Lock-In** für Editor/Charts — bringen Sie Ihre eigenen
  (Sister-Pack-Adapter: `quill`, `tinymce`)

## Sister-Packs

Optionale Erweiterungen, installieren Sie nur was Sie brauchen:

| Paket | Zweck |
|---|---|
| `dskripchenko/laravel-admin-starter` | User/Role/Audit/Settings/Translations/Blocks Resources |
| `dskripchenko/laravel-admin-tinymce` | TinyMCE WYSIWYG-Adapter |
| `dskripchenko/laravel-admin-quill` | Quill WYSIWYG-Adapter |
| `dskripchenko/laravel-admin-search` | ⌘K Command Palette + Scout Suggest |
| `dskripchenko/laravel-admin-media` | Medienbibliothek (ohne Spatie/medialibrary) |
| `dskripchenko/laravel-admin-health` | Health-Checks (ohne Spatie/laravel-health) |
| `dskripchenko/laravel-admin-pulse` | Telemetrie (ohne laravel/pulse) |
| `dskripchenko/laravel-admin-jobs` | Failed-Jobs / Batches Viewer |

## Mitwirken

Siehe [CONTRIBUTING.md](CONTRIBUTING.md). PRs willkommen.

## Lizenz

[MIT](LICENSE) © Denis Skripchenko

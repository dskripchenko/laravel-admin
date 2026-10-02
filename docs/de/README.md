# dskripchenko/laravel-admin

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · **Deutsch** · [中文](../zh/README.md)

Ein Laravel Admin-Panel-Konstruktor im Stil von Orchid mit einem Vue 3 SPA-Frontend.

[![npm](https://img.shields.io/npm/v/@dskripchenko/laravel-admin?label=%40dskripchenko%2Flaravel-admin)](https://www.npmjs.com/package/@dskripchenko/laravel-admin)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin)](https://packagist.org/packages/dskripchenko/laravel-admin)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin)](../../LICENSE)

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
- **i18n** — Locale-Resolver (6-Stufen-Priorität),
  `TranslatableField`-Bridge für
  `dskripchenko/laravel-translatable`.
- **Mandantenfähigkeit** — `TenantResolver` / `TenantContext` /
  `TenantScoped`-Trait. Strategie ist host-seitig; wir liefern den
  Vertrag.
- **Plugins** — `AdminPlugin`-Interface; Sister-Packs nutzen denselben
  Hook.
- **Testing** — `AdminTestCase`, Traits `ActsAsAdmin` und
  `InteractsWithAdminResources`.
- **OpenAPI 3.0** — generiert aus Docblock-Tags `@input`/`@output`.

## Installation

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

Das war's — die Admin-SPA wird vorgebaut ausgeliefert, Node ist nicht
nötig. Öffnen Sie `/admin/login`. [Erste Schritte](getting-started.md)
zeigt die erste Resource und den Custom-Build-Modus für eigene
Vue-Komponenten.

## Dokumentation

- [Erste Schritte](getting-started.md)
- [Admin in eine bestehende Anwendung integrieren](integration.md)
- [Architektur](architecture.md)
- Konzepte: [Resources](concepts/resources.md) ·
  [Screens](concepts/screens.md) ·
  [Widgets & Dashboards](concepts/widgets-and-dashboards.md) ·
  [Menü](concepts/menu.md) ·
  [Actions](concepts/actions.md) ·
  [Berechtigungen](concepts/permissions.md) ·
  [i18n](concepts/i18n.md) ·
  [Mandantenfähigkeit](concepts/tenancy.md)
- [Felder-Referenz](fields-reference.md)
- [Layouts-Referenz](layouts-reference.md)
- [API-Referenz](api-reference.md)
- [Frontend-Erweiterung](frontend-extension.md)
- [Testen](testing.md)
- [Migrationsleitfaden](migration-guide.md)
- [Demo-Modus](demo-mode.md)
- [Glossar](glossary.md)

## Stack

- **PHP** ^8.2
- **Laravel** 11 / 12 / 13
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Frontend** — vorgebaute SPA ~350 KB gz JS + ~35 KB gz CSS; npm-Bibliothek
  für eigene Builds ~260 KB gz (Vue, Pinia und UI-Kit extern)
- **WYSIWYG** — standardmäßig `@dskripchenko/wysiwyg`; Quill- und
  TinyMCE-Adapter liegen im npm-Paket (`/quill`, `/tinymce`)

## Sister-Packs

Optionale Erweiterungen, installieren Sie nur was Sie brauchen:

| Paket | Zweck |
|---|---|
| `dskripchenko/laravel-admin-starter` | Resources für Benutzer, Rollen und Audit-Log |
| `dskripchenko/laravel-admin-media` | Medienbibliothek (ohne Spatie/medialibrary) |
| `dskripchenko/laravel-admin-health` | Health-Checks (ohne Spatie/laravel-health) |
| `dskripchenko/laravel-admin-pulse` | Telemetrie (ohne laravel/pulse) |
| `dskripchenko/laravel-admin-jobs` | Failed-Jobs / Batches Viewer |

## Mitwirken

Siehe [CONTRIBUTING.md](../../.github/CONTRIBUTING.md). PRs willkommen.

## Lizenz

[MIT](../../LICENSE) © Denis Skripchenko

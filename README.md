# dskripchenko/laravel-admin

> 🌐 **English** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [中文](README.zh.md)

A Laravel admin-panel constructor inspired by Orchid, with a Vue 3 SPA frontend.

[![npm](https://img.shields.io/npm/v/@dskripchenko/laravel-admin?label=%40dskripchenko%2Flaravel-admin)](https://www.npmjs.com/package/@dskripchenko/laravel-admin)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin)](https://packagist.org/packages/dskripchenko/laravel-admin)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin)](LICENSE)

```php
Admin::resources([UserResource::class, ArticleResource::class]);
Admin::screen([ContactScreen::class, SystemStatusScreen::class]);
Admin::menu()->add(
    MenuNode::make('content', 'Content')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::dashboard('analytics'),
    ]),
);
```

## What's inside

- **CRUD pipeline** — declare an Eloquent model as a `Resource`, get
  list/create/edit/view screens for free.
- **Custom Screens** — non-CRUD pages (forms, dashboards, reports) with
  `Admin::screen()`. Handles state, layout, command-bar, validation,
  permissions.
- **Hierarchical menu** — fluent `Admin::menu()->add(MenuNode::...)`,
  any depth, auto-resolve `resource()`/`screen()`/`dashboard()`.
- **30+ field types** — Input/Number/Select/Combobox/DatePicker/
  ColorPicker/FileUpload/Wysiwyg/Markdown/TranslatableInput/Repeater/
  RelationSelect/Cascader/TreeSelect/Slug/KeyValue/TagsInput/...
- **15+ layouts** — Rows/Columns/Tabs/Wizard+Step/Block/Modal/Drawer/
  Wrapper/Infolist/Dashboard/Accordion/View/...
- **Tables** — sortable columns, presets, filters (input/date/switcher/
  options/select-from-model), inline-edit, summary, saved views,
  group-by, polling, exports (CSV/XLSX/PDF).
- **Dashboard** — 8 widget types (Stats/Chart/RecentList/Markdown/
  Iframe/Table/Heatmap/Gauge), per-user layout overrides, drag/resize,
  polling.
- **Auth & RBAC** — multi-guard, AdminUser, Roles, 2FA TOTP, profile,
  impersonation, password reset, email verification.
- **Audit** — append-only log of admin actions (`AuditLog` + `Loggable`
  trait).
- **Settings** — singleton-style configuration screens.
- **Notifications** — bell badge + drawer (Database notifications).
- **API tokens** — Sanctum integration in Profile (conditional).
- **Theming** — light/dark + per-user preference, `@dskripchenko/ui`
  design tokens.
- **i18n** — locale resolver (5-step priority), `TranslatableField`
  bridge for `dskripchenko/laravel-translatable`.
- **Tenancy** — `TenantResolver` / `TenantContext` / `TenantScoped`
  trait. Strategy is host-side; we provide the contract.
- **Plugins** — `AdminPlugin` interface; sister-packs use the same hook.
- **Testing** — `ResourceTestCase`, `ScreenTestCase`, `ActsAsAdmin` trait.
- **OpenAPI 3.0** — generated from docblock `@input`/`@output` tags.

## Install

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

Visit `/admin/login`. See [getting-started.md](docs/en/getting-started.md)
for the first resource.

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Architecture](docs/en/architecture.md)
- Concepts: [Resources](docs/en/concepts/resources.md) ·
  [Screens](docs/en/concepts/screens.md) ·
  [Widgets & Dashboards](docs/en/concepts/widgets-and-dashboards.md) ·
  [Menu](docs/en/concepts/menu.md) ·
  [Actions](docs/en/concepts/actions.md) ·
  [Permissions](docs/en/concepts/permissions.md) ·
  [i18n](docs/en/concepts/i18n.md) ·
  [Tenancy](docs/en/concepts/tenancy.md)
- [Fields reference](docs/en/fields-reference.md)
- [Layouts reference](docs/en/layouts-reference.md)
- [API reference](docs/en/api-reference.md)
- [Frontend extension](docs/en/frontend-extension.md)
- [Testing](docs/en/testing.md)
- [Migration guide](docs/en/migration-guide.md)
- [Glossary](docs/en/glossary.md)

## Stack

- **PHP** ^8.5
- **Laravel** ^12
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Bundle** — `@dskripchenko/laravel-admin` ~62 KB gz (esm + cjs)
- **No vendor lock-in** for editor/charts — bring your own
  (sister-pack adapters: `quill`, `tinymce`)

## Sister-packs

Optional extensions, install only what you need:

| Package | Purpose |
|---|---|
| `dskripchenko/laravel-admin-starter` | User/Role/Audit/Settings/Translations/Blocks resources |
| `dskripchenko/laravel-admin-tinymce` | TinyMCE WYSIWYG adapter |
| `dskripchenko/laravel-admin-quill` | Quill WYSIWYG adapter |
| `dskripchenko/laravel-admin-search` | ⌘K command palette + Scout suggest |
| `dskripchenko/laravel-admin-media` | Media library (no Spatie/medialibrary dependency) |
| `dskripchenko/laravel-admin-health` | Health checks (no Spatie/laravel-health dependency) |
| `dskripchenko/laravel-admin-pulse` | Telemetry sampler (no laravel/pulse dependency) |
| `dskripchenko/laravel-admin-jobs` | Failed jobs / batches viewer |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). PRs welcome.

## License

[MIT](LICENSE) © Denis Skripchenko

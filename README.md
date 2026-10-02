# dskripchenko/laravel-admin

> 🌐 **English** · [Русский](docs/ru/README.md) · [Deutsch](docs/de/README.md) · [中文](docs/zh/README.md)

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
- **Auth & RBAC** — multi-guard; a separate `admin_users` table or your
  existing users ([shared strategy](docs/en/integration.md#2-who-signs-in-dedicated-or-shared)),
  roles, 2FA TOTP, profile, impersonation, password reset, email
  verification.
- **Audit** — append-only log of admin actions (`AuditLog` + `Loggable`
  trait).
- **Settings** — singleton-style configuration screens.
- **Notifications** — bell badge + drawer (Database notifications).
- **API tokens** — Sanctum integration in Profile (conditional).
- **Theming** — light/dark + per-user preference, `@dskripchenko/ui`
  design tokens.
- **i18n** — locale resolver (6-step priority), `TranslatableField`
  bridge for `dskripchenko/laravel-translatable`.
- **Tenancy** — `TenantResolver` / `TenantContext` / `TenantScoped`
  trait. Strategy is host-side; we provide the contract.
- **Plugins** — `AdminPlugin` interface; sister-packs use the same hook.
- **Testing** — `AdminTestCase`, `ActsAsAdmin` and
  `InteractsWithAdminResources` traits.
- **OpenAPI 3.0** — generated from docblock `@input`/`@output` tags.

## Install

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

That's it — the admin SPA ships prebuilt, no Node needed. Visit
`/admin/login`. [Getting started](docs/en/getting-started.md) walks through
the first resource and the custom-build mode for your own Vue components.

Adding it to an application that already has users, its own API or a proxy?
Read [Adding the admin to an existing application](docs/en/integration.md) —
including `admin:install --shared`, which lets your existing users sign in.

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Adding the admin to an existing application](docs/en/integration.md)
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

- **PHP** ^8.2
- **Laravel** 11 / 12 / 13
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Frontend** — prebuilt SPA ~350 KB gz JS + ~35 KB gz CSS; the npm
  library entry for custom builds ~260 KB gz (Vue, Pinia and the UI kit external)
- **WYSIWYG** — `@dskripchenko/wysiwyg` by default; Quill and TinyMCE
  adapters ship in the npm package (`/quill`, `/tinymce`)

## Sister-packs

Optional extensions, install only what you need:

| Package | Purpose |
|---|---|
| `dskripchenko/laravel-admin-starter` | User, Role and Audit Log resources |
| `dskripchenko/laravel-admin-media` | Media library (no Spatie/medialibrary dependency) |
| `dskripchenko/laravel-admin-health` | Health checks (no Spatie/laravel-health dependency) |
| `dskripchenko/laravel-admin-pulse` | Telemetry sampler (no laravel/pulse dependency) |
| `dskripchenko/laravel-admin-jobs` | Failed jobs / batches viewer |

## Contributing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md). PRs welcome.

## License

[MIT](LICENSE) © Denis Skripchenko

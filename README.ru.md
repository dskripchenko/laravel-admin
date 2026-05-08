# dskripchenko/laravel-admin

> 🌐 [English](README.md) · **Русский** · [Deutsch](README.de.md) · [中文](README.zh.md)

Конструктор админ-панели для Laravel в духе Orchid — с собственным Vue 3 SPA-фронтендом.

[![npm](https://img.shields.io/npm/v/@dskripchenko/laravel-admin?label=%40dskripchenko%2Flaravel-admin)](https://www.npmjs.com/package/@dskripchenko/laravel-admin)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin)](https://packagist.org/packages/dskripchenko/laravel-admin)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin)](LICENSE)

```php
Admin::resources([UserResource::class, ArticleResource::class]);
Admin::screen([ContactScreen::class, SystemStatusScreen::class]);
Admin::menu()->add(
    MenuNode::make('content', 'Контент')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::dashboard('analytics'),
    ]),
);
```

## Что внутри

- **CRUD-pipeline** — объявите Eloquent-модель как `Resource` и
  получите list/create/edit/view-страницы автоматически.
- **Кастомные Screen'ы** — формы, отчёты, дашборды и любые non-CRUD
  страницы через `Admin::screen()`. State, layout, command-bar,
  валидация, permissions.
- **Иерархическое меню** — fluent API `Admin::menu()->add(MenuNode::...)`,
  любая глубина, auto-resolve `resource()`/`screen()`/`dashboard()`.
- **30+ типов полей** — Input/Number/Select/Combobox/DatePicker/
  ColorPicker/FileUpload/Wysiwyg/Markdown/TranslatableInput/Repeater/
  RelationSelect/Cascader/TreeSelect/Slug/KeyValue/TagsInput/...
- **15+ layout'ов** — Rows/Columns/Tabs/Wizard+Step/Block/Modal/Drawer/
  Wrapper/Infolist/Dashboard/Accordion/View/...
- **Таблицы** — sortable/searchable колонки, presets, фильтры,
  inline-edit, summary, saved views, group-by, polling, экспорт
  (CSV/XLSX/PDF).
- **Dashboard** — 8 типов виджетов (Stats/Chart/RecentList/Markdown/
  Iframe/Table/Heatmap/Gauge), per-user layout-override'ы, drag/resize,
  polling.
- **Auth & RBAC** — multi-guard, AdminUser, Roles, 2FA TOTP, profile,
  impersonation, password-reset, email-verification.
- **Audit** — append-only журнал админских действий
  (`AuditLog` + trait `Loggable`).
- **Settings** — singleton-страницы конфигурации.
- **Уведомления** — bell-badge + drawer (Database notifications).
- **API-токены** — Sanctum в Profile (опционально).
- **Темы** — light/dark + per-user, дизайн-токены `@dskripchenko/ui`.
- **i18n** — locale-resolver (5 уровней приоритета),
  `TranslatableField`-bridge с `dskripchenko/laravel-translatable`.
- **Tenancy** — `TenantResolver` / `TenantContext` / trait
  `TenantScoped`. Стратегия — на стороне host'а, мы даём контракт.
- **Plugins** — интерфейс `AdminPlugin`; sister-pack'и используют тот же hook.
- **Тестирование** — `ResourceTestCase`, `ScreenTestCase`, trait
  `ActsAsAdmin`.
- **OpenAPI 3.0** — генерируется из docblock-тегов `@input`/`@output`.

## Установка

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

Открой `/admin/login`. См. [getting-started.md](docs/ru/getting-started.md)
для первого resource'а.

## Документация

- [Быстрый старт](docs/ru/getting-started.md)
- [Архитектура](docs/ru/architecture.md)
- Концепции: [Resources](docs/ru/concepts/resources.md) ·
  [Screens](docs/ru/concepts/screens.md) ·
  [Widgets & Dashboards](docs/ru/concepts/widgets-and-dashboards.md) ·
  [Menu](docs/ru/concepts/menu.md) ·
  [Actions](docs/en/concepts/actions.md) (en) ·
  [Permissions](docs/en/concepts/permissions.md) (en) ·
  [i18n](docs/en/concepts/i18n.md) (en) ·
  [Tenancy](docs/en/concepts/tenancy.md) (en)
- [Каталог полей](docs/en/fields-reference.md) (en)
- [Каталог layout'ов](docs/en/layouts-reference.md) (en)
- [API reference](docs/en/api-reference.md) (en)
- [Frontend-расширение](docs/en/frontend-extension.md) (en)
- [Тестирование](docs/en/testing.md) (en)
- [Migration guide](docs/en/migration-guide.md) (en)
- [Глоссарий](docs/ru/glossary.md)

## Стек

- **PHP** ^8.5
- **Laravel** ^12
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Bundle** — `@dskripchenko/laravel-admin` ~62 KB gz (esm + cjs)
- **Без vendor lock-in** для редактора/чартов — подключай свой
  (sister-pack-адаптеры: `quill`, `tinymce`)

## Sister-пакеты

Опциональные расширения, ставь только нужные:

| Пакет | Назначение |
|---|---|
| `dskripchenko/laravel-admin-starter` | Resources для User/Role/Audit/Settings/Translations/Blocks |
| `dskripchenko/laravel-admin-tinymce` | TinyMCE WYSIWYG-адаптер |
| `dskripchenko/laravel-admin-quill` | Quill WYSIWYG-адаптер |
| `dskripchenko/laravel-admin-search` | ⌘K command palette + Scout suggest |
| `dskripchenko/laravel-admin-media` | Медиа-библиотека (без Spatie/medialibrary) |
| `dskripchenko/laravel-admin-health` | Health checks (без Spatie/laravel-health) |
| `dskripchenko/laravel-admin-pulse` | Telemetry (без laravel/pulse) |
| `dskripchenko/laravel-admin-jobs` | Failed jobs / batches viewer |

## Contributing

См. [CONTRIBUTING.md](CONTRIBUTING.md). PR'ы welcome.

## Лицензия

[MIT](LICENSE) © Denis Skripchenko

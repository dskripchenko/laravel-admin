# Changelog

All notable changes to `dskripchenko/laravel-admin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.3] - 2026-05-07

### Added

- **`@dskripchenko/wysiwyg` как default WYSIWYG** — собственный zero-dep
  редактор (~7 KB gzip). Заменил TextAreaField fallback в `wysiwyg`-field
  registry. `WysiwygField.vue` — тонкая обёртка над `DskWysiwyg`. Host
  может перебить через `registerField('wysiwyg', …)` (Quill/Tinymce
  subpath остались для совместимости).

## [1.2.2] - 2026-05-07

### Added

- **G1: i18n full migration** — `resources/lang/{ru,en}/admin.php` для core, `BootstrapBuilder.loadTranslations()` flatten в bootstrap.translations, `loadTranslationsFrom` в AdminServiceProvider. Frontend `tt(key, fallback)` обёртки в `ResourceIndexPage`. Host публикует override через `vendor:publish`.
- **G2: Built-in QR-encoder** — `lean-qr` (~3KB, MIT, без peer-dep) рендерит QR прямо в TwoFactorSetup. Slot `qr-code` остаётся для override.
- **G3: Drop-indicator при reorder** — `dragOverRowIdx` + `dragOverSide` управляют горизонтальной линией перед/после строки. Ghost-style на исходной строке (opacity:0.4).
- **G4: Backend tests** — 4 новых теста на `/{slug}/action` endpoint (success / 404 / 422 / second-action). Total 783 (+4).

### Notes

- **G5**: sister-pack repos локально не клонированы — host'у нужно вручную тегнуть `v1.2.0` на каждом из 8 пакетов (starter/jobs/health/media/pulse/search/quill/tinymce). Core 1.2.x не делает breaking changes для них.

## [1.2.1] - 2026-05-07

### Added

- **F1: Reorder-row UI** — drag-handle column для resource'ов с `reorderable=true`, HTML5 drag, persistence через `POST /{slug}/reorder`.
- **F2: Bootstrap translations** — `BootstrapBuilder` кладёт lang-bag из admin::*-namespace в payload (пара ключ/перевод), frontend `useI18nStore` гидрирует.
- **F4: QR slot** в TwoFactorSetup — host подключает любой QR-генератор через `<template #qr-code>`. Demo: `qrcode-svg`.
- **F5: JSON exporter** — без зависимостей, поддержка `lines` режима (NDJSON). `Export CSV/JSON/XLSX/PDF` пункты в more-menu.
- **F3: i18n migration scaffolding** — `t(key, fallback)`-обёртки на DashboardPage с graceful ru-fallback. Прогон остальных компонентов — следующий спринт.

## [1.2.0] - 2026-05-07

### Added

**Frontend**:
- Dashboard widgets edit-mode (drag/resize/add/configure) с layout persistence per-user.
- WidgetConfigDialog: per-type config editor (markdown/stat/gauge/chart/recent), заменил `window.prompt`.
- Toast-сервис: `useToast()` смонтирован глобально, `adminToast.*` helper, `window.alert/confirm` вытесняются.
- Drag-handle isolation в DashboardPage (только за `[☰]`).
- Date-range фильтр прокинут в widgets через `GET /dashboard/widgets?period=`.
- Inline-edit cells: double-click → input → Enter (`POST /{slug}/inlineUpdate`).
- Soft-delete UI: per-row Restore/Force-delete + автоматический Trashed-фильтр.
- 2FA setup wizard (`TwoFactorSetup.vue`).
- API tokens manager (`ApiTokensManager.vue`).
- Impersonation banner (auto-detect + exit).
- Forgot/reset password страницы.
- i18n базовый: `useI18nStore` + `t()` helper.
- TranslatableField — input с табами по локалям.

**Backend**:
- `Resource::meta().subject_type` — морф-class модели для AuditTimeline.
- Auto-inject TrashedFilter в `meta()` для SoftDeletes.
- `ResourceController::action()` — generic dispatcher `POST /{slug}/action`.
- `DashboardController::widgets()` — `GET /dashboard/widgets?key=&period=`.
- `DashboardScreen::withPeriod() / periodDays()` — period propagation.
- `Manifest::build()` сериализует dashboards из ScreenRegistry.
- `Role::hasPermission()` через `fnmatch()` — middle-segment wildcards.

### Changed

- Снят `final` с встроенных Widget-классов (extends-friendly).
- `FieldRenderer` разворачивает `attributes` из manifest'а на верхний уровень props.

### Demo

- `ContentDashboardScreen` (10 виджетов по эталону).
- DemoSeeder с baseline-ролями (`super-admin` / `editor` / `viewer`).
- Quill через `defineAsyncComponent` — lazy load (~200 KB меньше initial bundle).

## [Unreleased]

### Added
- Initial scaffold (composer/package skeleton, AdminServiceProvider, base config).
- Architecture document at `docs/ARCHITECTURE.md`.
- Sister-pack specifications at `docs/sister-packs/`.

# Changelog

All notable changes to `dskripchenko/laravel-admin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

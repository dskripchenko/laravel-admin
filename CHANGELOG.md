# Changelog

All notable changes to `dskripchenko/laravel-admin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [npm 1.9.2] - 2026-07-22

### Fixed
- DB-driven select options (model-backed `options()` serialized into the
  manifest) went stale within an SPA session: creating a group didn't add it
  to «Родитель»-selects until a full page reload. The resource form store now
  invalidates the cached manifest after successful save/delete; the next
  page mount refetches it.

## [npm 1.9.1] - 2026-07-22

### Fixed
- Builtin registration no longer clobbers host components: `registerField`/
  `registerWidget` calls made before `createAdminApp()` keep priority
  (builtins register only absent types). Previously the built-in bundle
  silently overwrote host overrides (e.g. a host `repeater` or a rich
  `markdown` dashboard widget).

## [npm 1.9.0] - 2026-07-22

### Added — complex field components (roadmap backlog closed)
- `KeyValueField` (`key_value`) — object editor with key/value rows,
  addable/removable, `allowedKeys` datalist.
- `RepeaterField` (`repeater`) — list of sub-field groups with add/remove/
  reorder, `minItems`/`maxItems`/`defaultItem`; each item edits in a nested
  form state (`NestedFieldsGroup`).
- `BuilderField` (`builder`) — typed content blocks from `Builder::block()`
  definitions: add-from-catalog, remove, reorder, per-block nested sub-form.
- `RelationTableField` (`relation_table`) — read-only related-records table
  with resource-format columns and `formatCell` presets.

## [1.9.2] - 2026-07-22

### Added
- `admin:user --super` actually assigns the system Super Admin role
  (idempotent by `super-admin` slug, permissions `['*']`, `is_system`).
- Session hardening: `AdminAuth` re-validates a session-stored password hash
  on every request — changing a user's password elsewhere invalidates their
  other sessions (own session survives a profile password change;
  impersonation refreshes the hash on start/stop). Deactivated accounts
  (`is_active`/`enabled` = false) are cut on the next request, not only at
  login.

### Fixed
- `ValidationRulesExporter` silently dropped object rules (`Rule::unique()`
  et al.) — they never reached the validator. Objects now pass through to
  validation; the manifest keeps serializing string rules only.

## [npm 1.8.1] - 2026-07-22

### Fixed — F10 Dashboard polish (staging E2E findings)
- First-ever layout save no longer 422s: entering edit mode seeds the draft
  with the merged manifest layout, so `/dashboard/save` always receives the
  full widget list (empty `widgets` failed `required` validation).
- «Сбросить» button added to the edit toolbar — the store's
  `resetToDefault()` (POST /dashboard/reset) had no UI.
- Toolbar labels went through i18n (`admin.dashboard.*`): «Add widget» /
  «Export» hardcodes replaced; ru/en lang files extended with
  `reset_layout` / `reset_confirm`.

## [npm 1.8.0] - 2026-07-22

### Added — F10 Dashboard complete
- `TableWidget` — full resource-format table on dashboards (backend
  `TableWidget` columns via `TableColumn::toArray()`; cells rendered with the
  same `formatCell` presets as the resource list: date/money/boolean/…).
- `IframeWidget` — sandboxed embed (`{src, height, sandbox}` from backend
  `IframeWidget::data()`).
- The builtin widget bundle now covers every backend `Widget::widgetType()`
  string — `table` and `iframe` previously rendered the UnknownWidget
  placeholder. New types automatically appear in AddWidgetDialog.

## [1.9.0] - 2026-07-22

Stable cut of the 1.8.x series: Panels are production-proven on the pilot
project (printable — two independent surfaces, full E2E scenario run of both
panels on staging). No code changes on top of 1.8.9 — this release pins the
stable pairing of composer v1.9.0 + npm `@dskripchenko/laravel-admin` 1.7.0.

## [1.8.9] - 2026-07-22

### Fixed
- Panel user models implementing only the `hasAccess()` contract received an
  empty permissions list in the SPA (login payload / bootstrap) — frontend
  route guards redirected them to /forbidden while the backend authorized the
  same requests. The shared `Permission\UserPermissions` resolver now hands
  such models a `['*']` wildcard (authorization stays server-side); models
  with `getAllPermissions()` are unchanged.

## [1.8.8] - 2026-07-22

### Fixed
- Per-action middleware executed twice (route registration + a second
  Pipeline pass in `RunActionMiddleware`) — every login burned 2+ throttle
  hits, so 429 arrived on the 3rd attempt instead of the 6th.
- Auth throttle buckets are per-panel now (`auth-{panelId}`): failed logins
  into one panel no longer lock the other for the whole IP.
- `Field::default()` now prefills create forms (npm 1.6.3): manifest defaults
  are seeded into state+initial (no false isDirty; query-prefill wins) — a
  required select with a default no longer fails validation out of the box.
- A throttled login shows a human message instead of the raw axios error.

## [1.8.7] - 2026-07-21

### Added
- Form-mode field visibility: `FieldRenderer` hides fields with
  `visibility[mode]=false` — `Field::onCreate(false)/onUpdate(false)` now
  affects rendering, not just validation (enables the create-password /
  rotate-password split pattern).
- `unique` rules on update automatically ignore the current record (string
  rules and `Rule::unique` objects).

### Fixed
- `dbExceptionToValidation()` put field messages under `errors`, but the SPA
  reads `payload.messages` — DB-level violations (unique/not-null/FK) never
  highlighted the offending fields.

## [1.8.6] - 2026-07-21

### Fixed
- The builtin frontend bundle registered only dash-cased component keys while
  `Field::fieldType()` emits snake_case — `relation_select`,
  `morph_switcher`, `tree_select`, `date_range`, `color` all rendered the
  UnknownField placeholder. Snake_case keys are registered now (npm 1.6.2);
  dash variants remain as aliases.
- `RelationSelect::toArray()` auto-eager-loads options from the related model
  when the host didn't set them — the SPA select has no async search, so an
  option-less relation select was unusable.

## [1.8.5] - 2026-07-21

### Fixed
- `SessionGuard` fires `Login`/`Logout` itself; the auth controller
  dispatched the same events again (completeLogin, logout, password-reset
  auto-login) — listeners such as the audit log received every auth event
  twice.

## [1.8.4] - 2026-07-21

### Fixed
- Unnamed `ThrottleRequests` middleware share one per-IP counter: the global
  `:60,1` api throttle burned the `:5,1` login limit — a handful of ordinary
  API requests produced 429 on login. Auth endpoints now pass explicit
  throttle prefixes.

## [1.8.3] - 2026-07-21

### Fixed
- Panel-aware auth: `AuthController`/`SystemController`/
  `ImpersonationManager` resolved the user provider from
  `config('admin.auth.provider')` regardless of the current panel — logins
  into secondary panels always failed with invalid_credentials. Added
  `Panel::authProvider()/passwordBroker()/authModel()` +
  `Panels::current*()`; last_login columns are written only when the panel's
  user table has them.

## [1.8.2] - 2026-07-21

### Fixed
- `Input`: missing `@method $this type(string $type)` annotation (the HTML
  type override was documented in prose but invisible to static analysis).

## [1.8.1] - 2026-07-21

### Changed
- `AdminApiModule` is no longer `final`: host modules that stitch the admin
  API together with their own laravel-api versions now extend it and merge
  `parent::getApiVersionList()` — panel versions arrive automatically instead
  of being re-declared by hand.

## [1.8.0] - 2026-07-21

### Added — Panels

Multiple independent admin surfaces on one core (Filament-Panels parity):

- **`Panel` / `PanelRegistry`** — each panel is a full vertical: its own mount
  path (including `''` — the site root), auth guard (+provider/model/password
  broker, registered at runtime like the default one), laravel-api version
  (`/api/{panel}/{controller}/{action}`), shell middleware stack and plugin
  set. Top-level config keys form the implicit default `admin` panel —
  single-panel hosts change nothing.
- **Registry scoping** — resources, screens, settings, widgets, menu trees and
  permission groups registered from a panel's plugins are tagged with the
  panel id; manifests, menus, auto-fill and the permissions endpoint are built
  per panel. Cross-panel resource access via another panel's API returns 404.
- **`Panel\PanelApi`** — base Api class for extra panels: inherits the whole
  system surface (bootstrap/auth/profile/uploads/notifications/resources),
  compiles only its panel's resources and does not inherit the parent
  version's method merge. Panel `middleware.api` entries are additions to the
  shared base stack (which is panel-aware via `Panels::currentGuard()`).
- **Root mount** — a panel with `path: ''` registers a catch-all that skips
  configured `exclude_prefixes` (`api`, `admin`, host routes) via a negative
  lookahead, and panels register from the most specific prefix down.
- **`Panels::currentGuard()`** — all core guard reads (24 call sites) now
  resolve through the current panel; bootstrap payload carries `panel`,
  per-panel `baseUrl`/`apiUrl` (frontend already derives its router base from
  them — the same SPA bundle serves every panel).

### Fixed
- `RunActionMiddleware` read per-action middleware from a hardcoded
  `AdminApi` — now resolves the current request's Api version, so per-action
  guards apply to panel APIs as well.

### Dependencies
- `dskripchenko/laravel-api` ^5.1.1 (protected `getNormalizedMethods`).

## [1.7.2] - 2026-07-21

### Fixed

- `BootstrapBuilder` no longer computes `manifestVersion` for guests: the login
  page does not need the resource manifest, while building it executes host
  resource code (DB-driven field options) before authentication — with
  auth/tenancy-scoped data sources that crashed the shell (HTTP 500) for
  unauthenticated visitors. The bootstrap contract already types
  `manifestVersion` as `string | null`.

## [1.7.1] - 2026-07-20

### Fixed

- Frontend lint/type errors: drop unused `catch` bindings, split `v-if`/`v-for`
  on the loading skeleton into a `<template>` wrapper, and extract a typed
  `inlineRowId()` helper (removing a template type-cast that ESLint mis-read as
  a deprecated Vue filter).

## [1.7.0] - 2026-07-20

### Changed

- **Расширена матрица поддержки:** PHP 8.2–8.5 (было только 8.5) и Laravel
  11/12/13 (было только 12). Зависимость `dskripchenko/laravel-api` поднята
  до `^5.0`. CI гоняет всю матрицу (с карв-аутом для EOL Laravel 11).

### Fixed

- `SchemaIntrospector::relationType()` определял `MorphTo`/`MorphToMany` как
  `BelongsTo`/`BelongsToMany` (подкласс проверялся после родителя) — порядок
  исправлен.
- Дополнена PHPDoc-схема `$col` в `FieldTypeInferrer::inferColumnCode()`
  (`enum_values`).

## [1.6.0] - 2026-06-16

### Added

- **Tree-view для иерархических ресурсов.** Resource с self-reference
  `parent()`/`children()` relation (или явным `hierarchyParentKey()`)
  компилируется в `GeneratedTreeScreen` вместо list-таблицы. Новые
  endpoint'ы `{resource}.treeScreen` (GET) и `{resource}.tree` (POST,
  отдаёт свёрнутое дерево с применением filters + `?q=`). Hook'и
  `treeNodeActions()` (per-node toolbar), `treeAdditionalRowIds()` и
  `treeExtraLeaves()` (cross-resource leaves — например шаблоны под своей
  группой), `parentSlug()` (back-link на чужой index). Фронт —
  `ResourceTreePage.vue` (search/expand/collapse/select/navigate).
  `make:section --tree` авто-детектит иерархию и генерирует
  `hierarchyParentKey()`.
- **Embedded resource table** — layout `Layout\ResourceTable` (тип
  `admin.resource-table`) для встраивания таблицы дочернего ресурса в
  форму родителя по FK. Поддерживает `hideColumns()`, `parentField()`,
  features create/delete/bulkDelete. Фронт — `EmbeddedResourceTable.vue`
  (inline-edit, quick-add, per-row + bulk delete).
- **Per-row inline edit** — `Resource::editableForRow($row, $column)`
  даёт точечный контроль редактируемости ячейки конкретной строки;
  ResourceController отдаёт `_editable` map в данных строки.
  `TableColumn::editable()` принимает `$as`
  (`text|number|select|date|textarea|switcher`) и `$options` для select.
- **File / Image поля** — `FileField.vue` (drop-zone, image-режим) и
  `ImageCropperField.vue` (canvas-кроппер с aspect-lock). Новый endpoint
  `uploads.serve` (GET) стримит файлы с whitelist-дисков
  (`config admin.uploads.servable_disks`) — preview для private-дисков
  без `storage:link`.
- **WysiwygField** — загрузка/ресайз (aspect-lock) и drag-n-drop
  переупорядочивание картинок прямо в редакторе.
- **ResourceFormPage** — pre-fill полей формы из query-параметров URL
  (`defaultsFromQuery`) при создании записи.
- **MenuRegistry::hideAuto($slug)** — исключение resource/screen из
  auto-fill sidebar (для ресурсов, встроенных в родителя).

## [1.5.6] - 2026-05-25

### Fixed

- **AuditTimeline** — diff-строки больше не показывают зачёркнутый `∅` как
  «было» на событиях `created`/`restored` и как «стало» на
  `deleted`/`destroyed`: бессмысленная колонка скрыта. Колонки имени поля
  и значения теперь выровнены через единый `display: grid` с
  `display: contents` на строке — раскладка одинакова в пределах всего
  diff'а независимо от длины имени поля.
- **AuditTimeline** — вертикальная линия timeline'а больше не уходит ниже
  иконки последнего события. Перерисована как per-item `::after`-коннектор
  (`:not(:last-child)`), который наследует `padding-left` от элемента —
  пропало 2px-смещение от центра иконки.
- **TagsField** — выпадающий список подсказок больше не обрезается
  ancestor'ами с `overflow`. Dropdown перенесён в `<Teleport to="body">`,
  позиция считается через `usePopover` (то же поведение, что и в
  `UidSelect`). Ширина dropdown'а синхронизирована с шириной chip-инпута,
  позиция пересчитывается на scroll/resize.
- **AdminAuth** — exclude-middleware (public-эндпоинты вроде `auth/login`)
  теперь читается у фактической API-версии текущего запроса через
  `ApiModule::getApi()` с фолбэком на `AdminApi`. Раньше бралось из
  фиксированного `AdminApi`, из-за чего host, сшивший admin API с другими
  версиями (external-v1) в одном laravel-api модуле, ломал определение
  public-роутов.

### Changed

- **Resource::infolist() default** — `switch`-поля (Switcher) теперь
  автоматически рендерятся как `IconEntry` с локализованными Да/Нет
  (`admin.common.yes`/`admin.common.no`, иконки `check-circle-2`/`x-circle`)
  вместо сырого `TextEntry`. Раньше view-страница без override'а показывала
  boolean-флаги как «true»/«false»; теперь оформление выровнено с тем, что
  даёт явный IconEntry в кастомном infolist'е. Override в подклассе
  по-прежнему имеет приоритет.

## [1.4.0] - 2026-05-08

### Added

- **Custom Screens API** (`Admin::screen([...])`) — generic non-CRUD screens.
  - `Screen::compile()` отдаёт `{state, layout, command_bar, permissions, etag}`.
  - Backend `ScreenCompiler` + `ScreenController` (state GET + runMethod POST).
  - Frontend `useScreenStore` + `ScreenPage.vue` (двойной provide
    FormState+Record — Screen работает и как форма, и как Infolist).
- **Hierarchical menu** (`Admin::menu()`) — fluent API любой глубины.
  - `MenuNode::make/resource/screen/dashboard`, `->children([...])`,
    `MenuRegistry::under(parent, [...])`.
  - Frontend `AdminSidebarNode` рекурсивный: indent depth 0..2, после
    — stripe-mode (left-border с fading alpha по depth).
- **Widget polling** — `Widget::refresh(int $sec)` запускает auto-refetch
  на dashboard'е (один интервал на минимальный refresh из видимых widgets).
- **Widget vertical resize** — `Widget::rowSpan(int 1..6)` + dual-axis
  resize-handle на dashboard'е (drag по X = cols span, по Y = rows span).
- **Drag drop-indicator** на dashboard'е — accent-outline на cell-target
  + opacity 0.45 на источнике (без sortablejs deps).
- **E2E full-flow smoke** (`demo/e2e-full-flow.mjs`) — 10 шагов: login
  → menu → resources → dashboard edit → custom screen → notifications
  → profile → logout.

### Fixed

- **DashboardPage** — slug читается из `route.meta` (роутер строит
  /dashboard/{slug} как static path без props), `manifest.load()` и
  `dashboardStore.openDashboard()` вызываются в onMounted.
- **MenuNode::dashboard()** — auto-detect DashboardScreen → /dashboard/{slug},
  custom screens → /screens/{slug}.
- **WidgetRenderer** — фильтрует dashboard-meta поля (`size`/`span`/`rowSpan`/
  `kind`/`refresh`/`permission`/`slug`) из widgetProps. Backend Widget.size
  это grid-column-span, а UidGauge.size — pixels: ранее конфликтовало.
- **HeatmapWidget** — переписан с UidHeatmap (календарной) на CSS-grid
  для матричного rows×cols формата (соответствует backend HeatmapWidget).
- **ChartWidget** — читает `data.chartType` (backend) с fallback на `type`.
- **RecentTableWidget** — нормализует backend `column={column,label}` →
  UidTable `{key,label}`.
- **GaugeWidget** — принимает `thresholds` (backend) как alias к UidGauge
  `ranges`; `unit → suffix`; flex-центрирование внутри cell.
- **StatWidget** — читает backend `stats[]` массив (раньше ждал scalar
  `value` → отображал 0 при заполненной БД).
- **Bar/Donut empty-state** — «Нет данных за период» вместо пустого SVG.
- **DashboardPage render** — hidden-override корректно убирает manifest-widget
  (раньше hidden-item не удалялся из `bySlug` Map'а до skip-continue).
- **Drag** — pointerdown listener сохраняет `dragInitiated` (e.target в
  dragstart = cell, не handle, поэтому closest всегда был null).
- **NotificationController** — guard `Schema::hasTable('notifications')`,
  если default Laravel migration не запущен — возвращает empty result
  вместо 500.
- **SelectField** — `readonly` маппится в `disabled` для UidSelect
  (визуально согласовано с TextField/NumberField).
- **WidgetConfigDialog UX** — required-поля помечены * + footer hint
  «Заполните *-поля» при disabled save.

### Changed

- `grid-auto-rows: 140px` на dashboard-grid'е (раньше autoflow).
- Default rowSpan по типу widget'а: stat=1, chart/heatmap/markdown=2..3.
- Sister-packs (starter/health/jobs/media/pulse/search/quill/tinymce)
  без изменений — auto-fill старого flat-menu сохранён, integration с
  MenuNode/Screen API опциональна.

## [1.3.0] - 2026-05-08

### Added

- Custom Screens (P21+P22) и Hierarchical menu (M1+M2) — initial drop
  (см. v1.4.0 для consolidated changes).

## [1.2.4] - 2026-05-02

### Changed

- **`@dskripchenko/wysiwyg` 0.2.0** — peer-dep range расширен до `^0.2.0`.
  В default WYSIWYG-поле теперь доступны: markdown shortcuts (`# `, `- `,
  `1. `, `> `, ` ``` `), slash-commands popup (`/h1`, `/list`, …),
  таблицы (insert/addRow/addColumn/remove*), code syntax highlighting
  (js/ts/php/html/css/json), HTML→Markdown helper. Bundle wysiwyg'а
  вырос с 7 KB до 12 KB gz, peer-deps не изменились.

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

# Changelog

All notable changes to `dskripchenko/laravel-admin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.19.3

### Fixed
- **The loading bar had no accessible name.** A `role="progressbar"` without a
  label is announced as "progressbar" and says nothing about what is
  happening. Found by running axe-core over the sign-in screen.

### Added
- **Storybook** — `npm run storybook`. A screen can be viewed at any width
  with no backend and no data, which is how layout defects surface; the
  viewport presets match what the consuming app checks in CI.

## 1.19.2

### Fixed
- **The sign-in form sat off-centre on a phone, with the page scrolling
  sideways.** The card was a fixed 400px wide and its `max-width: 100%`
  resolved against a grid area that was itself wider than the screen. Width is
  now capped rather than fixed, and the page padding shrinks below 480px.
- **The profile showed only the left half of its form on a phone.** Its two
  columns never collapsed: the content column simply ran off the edge, out of
  reach — the page did not even scroll to it. Below 720px the layout is a
  single column and the section list becomes a row of tabs that scrolls
  horizontally on its own.

## [1.19.1] — 2026-08-04

### Fixed
- **On a phone the panel opened with the menu covering the whole screen.** The
  `collapsed` flag means "narrow sidebar" on the desktop and "drawer is open"
  on a narrow screen — different things sharing a single default. At 390px the
  panel greeted the user with a 240px menu over the content, and the collapse
  button sat underneath the drawer itself and could not be pressed: the only
  way to close the menu was tapping the dimmed area, which nobody knows about.
  Below the drawer threshold (768px, as in `UidSidebarLayout`) it now starts
  closed, closes after a menu navigation and expands again on returning to a
  wide screen.
- **The top bar did not fit a phone.** At 390px "Change locale" ended at pixel
  409 and the user menu at 465: switching the language and signing out from a
  phone were impossible, and because of the flex overflow the icons shrank to
  12px and stopped being buttons. Below 768px the search field (⌘K is
  unreachable there anyway) and the breadcrumbs, duplicated by the page title,
  are dropped; the buttons no longer shrink.
- **The page header and the dashboard were clipped with no way to scroll.** The
  action row (period, "Export", "Edit") was wider than the screen while the
  page host hides overflow — the buttons became unreachable. On a narrow screen
  the header and the action row now wrap, and dashboard widgets go into a
  single column with content-driven height: twelve columns at 390px turned them
  into strips, half of which ran off the edge.

  Found by walking three viewports on a live stand (printable,
  `tests/e2e/tools/viewports.mjs`).

## [1.19.0] — 2026-08-04

### Added
- **`Resource\ActionFailedException` — a legitimate action failure answers 422
  instead of 500.** A resource action that threw an exception always became a
  500: a connection check that could not reach a foreign database looked
  exactly like a crashed panel in monitoring, and a user's typo in a port
  number woke the on-call engineer. An action now has a way to say "it did not
  work, and here is why": a thrown `ActionFailedException` reaches the user as
  its message with status 422, while any other exception is still a 500 —
  existing hosts see no change in behaviour. Found by sweeping panel actions in
  printable.

## [1.18.3] — 2026-08-04

### Added
- **A model can close the door itself — `isDisabledForLogin()`.** Access is
  closed not only by the account's own switch but by the state of whoever owns
  it: a suspended account, an expired subscription, a terminated contract. The
  panel does not know those rules, so `Auth\AccountState` asks the model: if
  the method exists and returned `true`, sign-in is closed — identically at
  login and on every request. Previously such checks lived only in application
  middleware, so the user got a session and a "you are signed in" response,
  with the refusal arriving on the next request.

## [1.18.2] — 2026-08-04

### Fixed
- **A disabled panel user could sign in.** Login checked only `is_active` (an
  `AdminUser` column), while panel user models are disabled through an
  `enabled` column — such a user received a session and a "you are signed in"
  response with a list of permissions, and only the next request threw them out
  through `AdminAuth`. For an account disabled for a dismissed employee that is
  wrong in principle. The disabled check moved into `Auth\AccountState` — the
  same answer at login and on every request; the strict comparison against
  `false` was dropped along the way, since it made a `0` coming from databases
  without a boolean type count as enabled. Found by an onboarding scenario test
  in printable.

## [1.18.1] — 2026-08-04

### Fixed
- **A screen button answered 500 when the request carried no `payload`.** A
  screen method declares a parameter (usually `array $state`), the controller
  called it with no arguments, and an `ArgumentCountError` escaped — on every
  button of every screen. The panel always sends `payload`, so the UI never
  showed it, but any other client (an integrator, curl, a typo in code) got a
  bare 500. An empty request is now equivalent to "the button was pressed on an
  empty form": the method receives empty state and answers with its own
  validation, and if more than one argument is required a 422 arrives listing
  what is missing. Found by walking the screen contract in printable.

## [1.18.0] — 2026-08-03

### Changed
- **The panel shell is assembled in one beat instead of two.** Measured on a
  live stand: at 236 ms the top bar and an empty sidebar were painted, and the
  content area showed a page that does not exist in this installation (the
  HomePage stub saying "register a DashboardScreen"); the real dashboard and
  the menu items appeared only at 510 ms. For a quarter of a second the user
  looked at someone else's screen, and then everything jumped into place.

  The readiness gate is now shared (`useAppReady`): until the manifest and the
  menu arrive, the page is replaced by a skeleton of its geometry and the menu
  items by their silhouette, and the real content appears as a soft fade with
  no shift (the slide is kept for transitions between screens). A hung menu
  request does not hold the page: the gate opens on a 1.5 s safety timeout.
  The same gate used to cover only the 404 flash — it now covers the whole
  class of "we showed the wrong thing while loading".

## [1.17.1] — 2026-08-03

### Fixed
- **Creating a record with a file field failed validation.** The SPA is
  upload-first: the file goes through `/uploads/upload` and the create/update
  form carries `{disk, path}` — yet the server-side rule exporter added
  `file`/`mimes:*` to file fields, rejecting exactly the value shape the panel
  sends. No resource with a `FileUpload` could create a record through the
  panel. The contract is now upload-first: `array` plus string `disk`/`path`;
  file size and type are checked during the upload itself. Found by
  printable's `OptionsIntegrityTest` on font files.

## [1.17.0] — 2026-08-03

### Changed
- **Saved list views are enabled by a resource flag** — `Resource::savedViews()`,
  `false` by default like the other capabilities (`replicable`, `reorderable`,
  `importable`). Four `{slug}_views/*` routes used to be registered for every
  resource without exception, including those with nothing to save. The flag
  travels in the manifest (`features.savedViews`), and the panel uses the same
  flag to decide whether to request the list: before that every list sent a
  request which on most resources would answer 404.

  Hosts that use saved views need to return `true` in the resources
  concerned.

## [1.16.0] — 2026-08-02

### Removed
- **The `exportCsv` resource action.** It duplicated `export(format=csv)` —
  the same code by two paths and an extra operation in the spec of every
  resource. The panel only ever called `export`. The call now answers 404; the
  replacement is `export?format=csv`.

## [1.15.3] — 2026-08-02

### Fixed
- **The form stayed silent on validation errors.** The user pressed "Save"
  with an empty required field and got nothing: no highlight, no text.
  `ValidationError` read the field map only from `payload.messages`, while it
  arrived in `payload.errors` — that is where laravel-api's default
  `ValidationException` handler puts it. The admin panel's own registration
  (`AdminServiceProvider::registerExceptionHandlers`) never reached the
  application: `api_error_handler` was bound with `bind`, so the added handlers
  went to a discarded instance (fixed in laravel-api 5.6.1). The frontend now
  reads both shapes — regardless of the backend version.

## [1.15.2] - 2026-07-30

### Fixed
- A type error in 1.15.1: the TextField wrapper did not pass `vue-tsc`. The
  1.15.1 tag stays as it is — published tags are never rewritten; install
  1.15.2 instead.

## [1.15.1] - 2026-07-30

### Fixed
- **`Password::make()` rendered as an ordinary text field** — the secret was
  visible in clear text while typing. The `password`/`email`/`url`/`tel` fields
  all mapped to one TextField, which defaults to `type="text"`; the type is now
  taken from the registry key (an explicit `inputType` in the field attributes
  still wins). The mobile keyboard adapts to email and telephone input as
  well.

## [1.15.0] - 2026-07-29

### Changed
- **Resource actions are registered according to the resource's
  capabilities.** `tree`/`treeScreen` are registered only for hierarchical
  resources, `restore`/`forceDelete` only under SoftDeletes, `replicate` and
  `reorder` by their respective flags. All 21 actions used to be registered
  indiscriminately: on an ordinary resource more than half could only answer
  with an error, and the API map bloated (116 dead endpoints out of 843 in a
  pilot project). Calling an unsupported action is now a 404 rather than a
  409/422.
- Public descriptions: references to the SPA's internals and `{@see …}` were
  removed from the docblocks of actions that end up in OpenAPI.

## [1.14.0] - 2026-07-29

### Added
- i18n: string literals inside components are translated — ternary
  interpolations (`{{ x ? 'Sign in' : 'Signing in…' }}`), toasts, `confirm()`,
  prop defaults and route titles. 110 strings across 36 files; the
  `resources/lang/en.json` dictionary went from 178 to 292 keys.
- `createTitleGuard` translates `meta.title` — the single point through which
  the titles of system routes reach `document.title`.

### Changed
- Prop defaults (`emptyText`, `keyLabel`, `searchPlaceholder`, …) stay as raw
  strings and are translated at the point of use: `withDefaults` evaluates them
  when the module loads, before the store exists, so the translation would
  freeze at the language of the first load.

## [1.13.4] - 2026-07-29

### Fixed
- i18n: the impersonation banner ("You are signed in as … · impersonation
  mode") was hard-coded in Russian — the one place a manifest sweep does not
  see, because it renders only during impersonation.

## [1.13.3] - 2026-07-29

### Fixed
- i18n: `Layout::toArray` returns props TWICE (spread onto the top level for
  `v-bind` and under a `props` key) — only the second copy was localized, so
  tab labels in the manifest stayed in the source language.

## [1.13.2] - 2026-07-29

### Fixed
- i18n: tab labels (`Tabs::toArray`) and any option-dictionary attributes
  (`*Options`, e.g. `typeOptions` of custom host fields) are now localized —
  they stayed in the source language under the `en` locale.

## [1.13.1] - 2026-07-29

### Fixed
- i18n: Localize coverage widened — labels that serialization never reached
  are now translated: any `*Label` attributes (key-value, repeater, builder),
  layout `props` (tab labels), `OptionsFilter` options, and a screen's
  `name`/`description` in its compile step. The symptom: under the `en` locale
  the panel showed tabs, filters and screen titles in the source language even
  though the translations were in the dictionary.

## [1.13.0] - 2026-07-29

### Fixed
- i18n: picked up the strings the previous sweep (BL-11) did not see — text
  standing on its own line inside a tag (multi-line text nodes): the 404/403
  status pages, the sign-in and 2FA forms, the profile and API tokens, the
  import wizard, the filter toolbar, the notifications drawer and the
  key-value/repeater/builder fields. 56 strings across 24 components; the
  package dictionary grew to 176 keys.

## [1.12.6] - 2026-07-28

### Fixed
- **CRITICAL, panels**: the API class's global middleware (panel additions —
  layer activation, throttling) is now applied when the request matched
  laravel-api's generic route `api/{version}/{controller}/{action}` as well.
  That route carried only the base group, so — depending on the order in which
  routes were registered — panel screens and endpoints executed WITHOUT the
  panel layer (a multi-tenant host read from and wrote to the central schema
  instead of the tenant's). `RunActionMiddleware` now includes
  `methods['middleware']` in the pipeline; duplicates on specific routes are
  filtered out by inspecting the route stack.

## [1.12.5] - 2026-07-28

### Fixed
- Screen store: a `download_url` in a runMethod result now actually downloads
  the file (through a programmatic anchor) — the field was in the contract but
  never handled, so screen buttons like "Download…" (printable's translation
  file and the like) silently did nothing.

## [1.12.4] - 2026-07-28

### Fixed
- Screen store: the success banner (`lastMessage`) survived navigation to
  another screen — `load()` now clears it when the slug changes (reloading the
  same screen after a runMethod keeps the message).

## [1.12.3] - 2026-07-28

### Fixed
- i18n (BL-11, tail): AdminApp passes the panel locale into
  @dskripchenko/ui's `provideLocale()` — the primitives' built-in strings
  ("Select…", "Nothing found", the calendar and so on) are translated together
  with the interface. The ui-kit locale mechanism had existed since 1.1.x —
  admin simply never called it.

## [1.12.2] - 2026-07-28

### Fixed
- i18n: the remaining frontend strings are covered — the title and buttons of
  ResourceFormPage, the `createLabel` and record-count plurals of
  ResourceIndexPage, AuditTimeline (events, "System", relative time) and
  ResourceViewPage; the package dictionary holds 124 keys. An English walk
  through the panels is clean (what is left is the "Select…" placeholder in
  @dskripchenko/ui).

## [1.12.1] - 2026-07-28

### Fixed
- A re-release of 1.12.0 (the tag had been rewritten, and composer and the
  registry cache the reference): no changes relative to the final content.

## [1.12.0] - 2026-07-28

### Added
- i18n (BL-11, continuing 1.11.2): hard-coded frontend strings in components
  are translated through `tr()` (the key is the source string);
  `useI18nStore().tr()` plus a standalone `trSafe`. BootstrapBuilder mixes the
  locale's JSON translations into the bag (the package's
  `resources/lang/en.json`, ~95 strings, plus the host's
  `lang/{locale}.json`, where the host overrides the package). ~133 strings
  across 37 components.

## [1.11.2] - 2026-07-28

### Fixed
- i18n (BL-11): user-facing manifest strings are translated during
  serialization through JSON translations (`I18n\Localize`) — field
  label/help/placeholder/options, column, filter and action labels (plus
  confirm), infolist entries, screen and dashboard names and descriptions,
  widget titles, and a resource's label and group. The host no longer has to
  wrap strings in `__()`.
- The names of generated screens ("Create:" / "Edit:") and the "Create" button
  come from the lang bag (`admin::admin.*`) instead of being hard-coded.

## [1.11.1] - 2026-07-28

### Fixed
- Dashboard (BL-18): hidden (hidden-override) widgets can be brought back —
  the "Add widget" dialog gained a "Hidden widgets" section (restore); with an
  empty list it says "Nothing to add". Store: `restoreWidget()`.
- Removing a manifest widget that had already reached the draft performed a
  full remove — the render merge brought the widget back; a manifest widget is
  now always a hidden override (full removal applies to user-added widgets
  only).
- `ensureDraftReflectsRendered` rebuilt the draft from the visible widgets and
  silently lost hidden entries (hidden widgets "resurrected" after a resize or
  a config change).

## [1.11.0] - 2026-07-28

### Added
- Branding (BL-12): `brand.logo` is now an image everywhere — the sidebar
  renders an `<img>` (the URL used to be printed as text), and the
  Forgot/Reset pages take the brand from `bootstrap.brand`; LoginPage reads
  `useBrand()` as a fallback to its props. A new `brand.mark` key
  (`ADMIN_BRAND_MARK`) provides a short textual mark as a fallback when there
  is no image. CSS: the container plate is dropped from an auth logo that
  carries an `<img>`.

## [1.10.19] - 2026-07-24

### Added
- `asLink` cells in the table: the grid renders a real `<a>`, with row-level placeholders in the URL.

## [1.10.18] - 2026-07-24

### Added
- `transformRecord()` hook — the read-side counterpart of `fillModel()`.

## [1.10.17] - 2026-07-24

### Fixed
- The notification badge moved into the corner of its button and no longer covers the bell.

## [1.10.16] - 2026-07-24

### Fixed
- Dropped the bottom border of the sidebar header in the admin shell.

## [1.10.15] - 2026-07-23

### Fixed
- Two-factor `disable()` sends the password, so the button no longer answers 422 every time.

## [1.10.14] - 2026-07-23

### Fixed
- `config('admin.auth.login_throttle')` is wired into the routes instead of being ignored.

## [1.10.13] - 2026-07-23

### Added
- The admin API throttle is configuration-driven and defaults to 240 requests per minute.

## [1.10.12] - 2026-07-23

### Added
- A `data-testid` contract across the shell, which is what makes end-to-end selectors stable.

## [1.10.11] - 2026-07-23

### Fixed
- Autogeneration survives the create-form seeding race.

## [1.10.10] - 2026-07-23

### Added
- `Field\Generated` plus `GeneratedField.vue` (`generated-field`) — a string
  with cryptographically random generation (tokens and secrets):
  `crypto.getRandomValues` only, rejection sampling with no modulo bias;
  length/charset/autogenerate. Ported from printable's host implementation
  (BL-34/35).
- Lang bag: the `admin.search.*` and `admin.fields.generate` sections (ru/en).

### Changed
- `AdminNotification` is now `ShouldQueue` — the domain fan-out of
  notifications no longer blocks the request path.
- `Manifest::build()` is memoized per instance (locale|panel): bootstrap no
  longer builds the manifest twice per request (`version()` reuses the memo);
  a `flush()` was added.
- `Resource::$group` and the menu group are translated during serialization
  through `__()` (group i18n, idempotent for untranslated strings).
- The ⌘K palette: the input receives focus immediately on open (`autofocus`
  plus @dskripchenko/ui 1.1.3, whose focus trap prefers `[autofocus]`); the
  palette strings come from the lang bag.

## [1.10.9] - 2026-07-23

### Changed
- Switching the locale reloads the shell, which completes the panel translation work.

## [1.10.8] - 2026-07-23

### Changed
- `MenuNode` labels are translated per request rather than once at build time.

## [1.10.7] - 2026-07-23

### Added
- Branding taken from `config('admin.brand')` in the shell: logo, favicon and copyright line.

## [1.10.6] - 2026-07-23

### Added
- The dashboard remembers the selected period, exports to JSON, and shows an
  empty state that offers to add a widget.

## [1.10.5] - 2026-07-23

### Added
- Global ⌘K search across resources: `GET /system/search` and `GlobalSearch.vue`.

## [1.10.4] - 2026-07-23

### Added
- Audit `type_labels` and `resolveTypeLabel()`, so events read as words instead of raw types.

## [1.10.3] - 2026-07-23

### Fixed
- The profile page wires up the two-factor wizard that actually works.

## [1.10.2] - 2026-07-23

### Changed
- Version bump carrying the backlog train forward; no separate feature of its own.

## [1.10.0] - 2026-07-23

The printable backlog train (BL-1…36).

### Added
- A CSRF interceptor that refreshes a stale `X-CSRF-TOKEN`.
- A not-found screen for edit forms.
- A bulk toolbar whose actions are gated rather than silently inert.
- `TrashedFilter` injected into search automatically.
- Per-resource `importable()` and `exportable()`.
- `BadgeEntry` colours and labels.
- Inline checkboxes, and a `RecentListWidget` whose rows are clickable through `linkTo`.

### Fixed
- `manifest.refresh()` no longer nulls the manifest, which is what made forms appear to collapse.
- Shell and timeline alignment.
- The ⌘K palette focuses its input on open (`autofocus`, with @dskripchenko/ui 1.1.3
  preferring `[autofocus]` in its focus trap); palette strings come from the lang bag.

## [npm 1.9.3] - 2026-07-22

### Fixed
- SPA permission matching now mirrors backend `Role::hasPermission`
  (fnmatch): mid-pattern globs (`printable.*.view`) work in route guards
  and menu filtering — previously only trailing `.*` masks matched, so
  glob roles locked users out of allowed sections.

## [npm 1.9.2] - 2026-07-22

### Fixed
- DB-driven select options (model-backed `options()` serialized into the
  manifest) went stale within an SPA session: creating a group didn't add it
  to the "Parent" selects until a full page reload. The resource form store now
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
- A "Reset" button was added to the edit toolbar — the store's
  `resetToDefault()` (POST /dashboard/reset) had no UI.
- Toolbar labels went through i18n (`admin.dashboard.*`): the "Add widget" and
  "Export" hardcodes were replaced; the ru/en lang files were extended with
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

- **The support matrix is widened:** PHP 8.2–8.5 (previously 8.5 only) and
  Laravel 11/12/13 (previously 12 only). The `dskripchenko/laravel-api`
  dependency was raised to `^5.0`. CI runs the whole matrix (with a carve-out
  for the EOL Laravel 11).

### Fixed

- `SchemaIntrospector::relationType()` reported `MorphTo`/`MorphToMany` as
  `BelongsTo`/`BelongsToMany` (the subclass was checked after the parent) —
  the order was fixed.
- The PHPDoc shape of `$col` in `FieldTypeInferrer::inferColumnCode()` was
  completed (`enum_values`).

## [1.6.0] - 2026-06-16

### Added

- **A tree view for hierarchical resources.** A resource with a
  self-referencing `parent()`/`children()` relation (or an explicit
  `hierarchyParentKey()`) compiles into a `GeneratedTreeScreen` instead of a
  list table. New endpoints: `{resource}.treeScreen` (GET) and
  `{resource}.tree` (POST, returning a collapsed tree with filters and `?q=`
  applied). Hooks: `treeNodeActions()` (a per-node toolbar),
  `treeAdditionalRowIds()` and `treeExtraLeaves()` (cross-resource leaves —
  templates under their own group, for instance) and `parentSlug()` (a back
  link to another resource's index). The frontend is `ResourceTreePage.vue`
  (search/expand/collapse/select/navigate). `make:section --tree` detects the
  hierarchy automatically and generates `hierarchyParentKey()`.
- **Embedded resource table** — the `Layout\ResourceTable` layout (type
  `admin.resource-table`) embeds a child resource's table into the parent's
  form by foreign key. Supports `hideColumns()`, `parentField()` and the
  create/delete/bulkDelete features. The frontend is
  `EmbeddedResourceTable.vue` (inline edit, quick add, per-row and bulk
  delete).
- **Per-row inline edit** — `Resource::editableForRow($row, $column)` gives
  precise control over whether a particular row's cell is editable;
  ResourceController returns an `_editable` map in the row data.
  `TableColumn::editable()` accepts `$as`
  (`text|number|select|date|textarea|switcher`) and `$options` for a select.
- **File and image fields** — `FileField.vue` (a drop zone with an image
  mode) and `ImageCropperField.vue` (a canvas cropper with an aspect lock). A
  new `uploads.serve` endpoint (GET) streams files from whitelisted disks
  (`config admin.uploads.servable_disks`) — previews for private disks without
  `storage:link`.
- **WysiwygField** — uploading and resizing images (with an aspect lock) and
  reordering them by drag and drop right inside the editor.
- **ResourceFormPage** — form fields are pre-filled from URL query parameters
  (`defaultsFromQuery`) when creating a record.
- **MenuRegistry::hideAuto($slug)** — excludes a resource or screen from the
  auto-filled sidebar (for resources embedded into a parent).

## [1.5.6] - 2026-05-25

### Fixed

- **AuditTimeline** — diff rows no longer show a struck-through `∅` as the
  "before" value on `created`/`restored` events or as the "after" value on
  `deleted`/`destroyed`: the meaningless column is hidden. The field-name and
  value columns are now aligned through a single `display: grid` with
  `display: contents` on the row — the layout is identical across the whole
  diff regardless of field-name length.
- **AuditTimeline** — the vertical timeline line no longer runs below the icon
  of the last event. It was redrawn as a per-item `::after` connector
  (`:not(:last-child)`) that inherits `padding-left` from the element — the
  2px offset from the icon's centre is gone.
- **TagsField** — the suggestion dropdown is no longer clipped by ancestors
  with `overflow`. The dropdown moved into `<Teleport to="body">` and its
  position is computed through `usePopover` (the same behaviour as
  `UidSelect`). The dropdown width is synchronized with the chip input, and
  the position is recalculated on scroll and resize.
- **AdminAuth** — the exclude middleware (public endpoints such as
  `auth/login`) is now read from the API version that actually handles the
  current request, through `ApiModule::getApi()` with a fallback to
  `AdminApi`. It used to be taken from a fixed `AdminApi`, so a host that
  stitched the admin API together with other versions (external-v1) inside one
  laravel-api module broke the detection of public routes.

### Changed

- **`Resource::infolist()` default** — `switch` fields (Switcher) now render
  automatically as an `IconEntry` with localized Yes/No
  (`admin.common.yes`/`admin.common.no`, the `check-circle-2`/`x-circle`
  icons) instead of a raw `TextEntry`. A view page without an override used to
  show boolean flags as "true"/"false"; the presentation now matches what an
  explicit IconEntry gives in a custom infolist. An override in a subclass
  still takes precedence.

## [1.4.0] - 2026-05-08

### Added

- **Custom Screens API** (`Admin::screen([...])`) — generic non-CRUD screens.
  - `Screen::compile()` returns `{state, layout, command_bar, permissions, etag}`.
  - Backend `ScreenCompiler` plus `ScreenController` (state GET, runMethod POST).
  - Frontend `useScreenStore` plus `ScreenPage.vue` (a double provide of
    FormState and Record — a screen works both as a form and as an infolist).
- **Hierarchical menu** (`Admin::menu()`) — a fluent API of arbitrary depth.
  - `MenuNode::make/resource/screen/dashboard`, `->children([...])`,
    `MenuRegistry::under(parent, [...])`.
  - The frontend `AdminSidebarNode` is recursive: indent for depths 0..2, and
    stripe mode beyond that (a left border with alpha fading by depth).
- **Widget polling** — `Widget::refresh(int $sec)` starts an auto-refetch on
  the dashboard (a single interval taken from the smallest refresh among the
  visible widgets).
- **Widget vertical resize** — `Widget::rowSpan(int 1..6)` plus a dual-axis
  resize handle on the dashboard (dragging along X sets the column span, along
  Y the row span).
- **A drag drop-indicator** on the dashboard — an accent outline on the target
  cell and opacity 0.45 on the source (with no sortablejs dependency).
- **An end-to-end full-flow smoke test** (`demo/e2e-full-flow.mjs`) — ten
  steps: login → menu → resources → dashboard edit → custom screen →
  notifications → profile → logout.

### Fixed

- **DashboardPage** — the slug is read from `route.meta` (the router builds
  /dashboard/{slug} as a static path without props); `manifest.load()` and
  `dashboardStore.openDashboard()` are called in `onMounted`.
- **`MenuNode::dashboard()`** — auto-detects a DashboardScreen →
  /dashboard/{slug}, custom screens → /screens/{slug}.
- **WidgetRenderer** — filters dashboard meta fields
  (`size`/`span`/`rowSpan`/`kind`/`refresh`/`permission`/`slug`) out of
  widgetProps. The backend's `Widget.size` is a grid column span while
  `UidGauge.size` is pixels: the two used to collide.
- **HeatmapWidget** — rewritten from UidHeatmap (a calendar) onto a CSS grid
  for the matrix rows×cols format (matching the backend HeatmapWidget).
- **ChartWidget** — reads `data.chartType` (backend) with a fallback to
  `type`.
- **RecentTableWidget** — normalizes the backend's `column={column,label}` into
  UidTable's `{key,label}`.
- **GaugeWidget** — accepts `thresholds` (backend) as an alias for UidGauge's
  `ranges`; `unit → suffix`; flex centring inside the cell.
- **StatWidget** — reads the backend's `stats[]` array (it used to expect a
  scalar `value` and displayed 0 against a populated database).
- **Bar/Donut empty state** — "No data for the period" instead of an empty
  SVG.
- **DashboardPage rendering** — a hidden override now removes the manifest
  widget correctly (the hidden item was not deleted from the `bySlug` map
  before the skip-continue).
- **Drag** — the pointerdown listener stores `dragInitiated` (`e.target` in
  dragstart is the cell rather than the handle, so `closest` was always null).
- **NotificationController** — guarded by `Schema::hasTable('notifications')`;
  if the default Laravel migration has not been run it returns an empty result
  instead of a 500.
- **SelectField** — `readonly` maps to `disabled` for UidSelect (visually
  consistent with TextField/NumberField).
- **WidgetConfigDialog UX** — required fields are marked with `*` plus a
  footer hint "Fill in the * fields" when saving is disabled.

### Changed

- `grid-auto-rows: 140px` on the dashboard grid (autoflow before).
- The default rowSpan by widget type: stat=1, chart/heatmap/markdown=2..3.
- Sister packs (starter/health/jobs/media/pulse/search/quill/tinymce) are
  unchanged — the auto-fill of the old flat menu is preserved and integration
  with the MenuNode/Screen API is optional.

## [1.3.0] - 2026-05-08

### Added

- Custom Screens (P21+P22) and the hierarchical menu (M1+M2) — the initial
  drop (see v1.4.0 for the consolidated changes).

## [1.2.4] - 2026-05-02

### Changed

- **`@dskripchenko/wysiwyg` 0.2.0** — the peer-dependency range was widened to
  `^0.2.0`. The default WYSIWYG field now offers markdown shortcuts (`# `,
  `- `, `1. `, `> `, ` ``` `), a slash-command popup (`/h1`, `/list`, …),
  tables (insert/addRow/addColumn/remove*), code syntax highlighting
  (js/ts/php/html/css/json) and an HTML→Markdown helper. The wysiwyg bundle
  grew from 7 KB to 12 KB gzipped; peer dependencies are unchanged.

## [1.2.3] - 2026-05-07

### Added

- **`@dskripchenko/wysiwyg` as the default WYSIWYG** — our own zero-dependency
  editor (~7 KB gzipped). It replaced the TextAreaField fallback in the
  `wysiwyg` field registry. `WysiwygField.vue` is a thin wrapper over
  `DskWysiwyg`. A host can override it through `registerField('wysiwyg', …)`
  (the Quill/Tinymce subpaths remain for compatibility).

## [1.2.2] - 2026-05-07

### Added

- **G1: full i18n migration** — `resources/lang/{ru,en}/admin.php` for the core, `BootstrapBuilder.loadTranslations()` flattening into `bootstrap.translations`, `loadTranslationsFrom` in AdminServiceProvider. Frontend `tt(key, fallback)` wrappers in `ResourceIndexPage`. A host publishes its overrides through `vendor:publish`.
- **G2: built-in QR encoder** — `lean-qr` (~3 KB, MIT, no peer dependency) renders the QR code directly in TwoFactorSetup. The `qr-code` slot remains for overrides.
- **G3: a drop indicator during reorder** — `dragOverRowIdx` and `dragOverSide` drive a horizontal line before or after the row. The source row gets a ghost style (opacity 0.4).
- **G4: backend tests** — four new tests for the `/{slug}/action` endpoint (success / 404 / 422 / second action). 783 in total (+4).

### Notes

- **G5**: the sister-pack repositories are not cloned locally — the host has to tag `v1.2.0` manually on each of the eight packages (starter/jobs/health/media/pulse/search/quill/tinymce). Core 1.2.x introduces no breaking changes for them.

## [1.2.1] - 2026-05-07

### Added

- **F1: reorder-row UI** — a drag-handle column for resources with
  `reorderable=true`, HTML5 drag and persistence through
  `POST /{slug}/reorder`.
- **F2: bootstrap translations** — `BootstrapBuilder` puts the lang bag from
  the `admin::*` namespace into the payload (key/translation pairs) and the
  frontend `useI18nStore` hydrates from it.
- **F4: a QR slot** in TwoFactorSetup — a host plugs in any QR generator
  through `<template #qr-code>`. Demo: `qrcode-svg`.
- **F5: a JSON exporter** — dependency-free, with a `lines` mode (NDJSON).
  `Export CSV/JSON/XLSX/PDF` entries in the more-menu.
- **F3: i18n migration scaffolding** — `t(key, fallback)` wrappers on
  DashboardPage with a graceful fallback. The remaining components come in the
  next sprint.

## [1.2.0] - 2026-05-07

### Added

**Frontend**:
- A dashboard widget edit mode (drag/resize/add/configure) with per-user
  layout persistence.
- WidgetConfigDialog: a per-type config editor
  (markdown/stat/gauge/chart/recent) that replaced `window.prompt`.
- A toast service: `useToast()` mounted globally, an `adminToast.*` helper;
  `window.alert`/`confirm` are being phased out.
- Drag-handle isolation in DashboardPage (dragging only by `[☰]`).
- The date-range filter is passed to widgets through
  `GET /dashboard/widgets?period=`.
- Inline-edit cells: double click → input → Enter
  (`POST /{slug}/inlineUpdate`).
- Soft-delete UI: per-row Restore/Force delete plus an automatic Trashed
  filter.
- A 2FA setup wizard (`TwoFactorSetup.vue`).
- An API token manager (`ApiTokensManager.vue`).
- An impersonation banner (auto-detect plus exit).
- Forgot/reset password pages.
- Basic i18n: `useI18nStore` plus a `t()` helper.
- TranslatableField — an input with per-locale tabs.

**Backend**:
- `Resource::meta().subject_type` — the model's morph class for
  AuditTimeline.
- Auto-injection of TrashedFilter into `meta()` under SoftDeletes.
- `ResourceController::action()` — a generic dispatcher for
  `POST /{slug}/action`.
- `DashboardController::widgets()` — `GET /dashboard/widgets?key=&period=`.
- `DashboardScreen::withPeriod()` / `periodDays()` — period propagation.
- `Manifest::build()` serializes dashboards from the ScreenRegistry.
- `Role::hasPermission()` through `fnmatch()` — middle-segment wildcards.

### Changed

- `final` was dropped from the built-in Widget classes (extends-friendly).
- `FieldRenderer` spreads `attributes` from the manifest onto the top level of
  the props.

### Demo

- `ContentDashboardScreen` (ten widgets following the reference design).
- A DemoSeeder with baseline roles (`super-admin` / `editor` / `viewer`).
- Quill through `defineAsyncComponent` — lazy loaded (~200 KB off the initial
  bundle).

## [Unreleased]

### Added
- Initial scaffold (composer/package skeleton, AdminServiceProvider, base config).
- Architecture document at `docs/ARCHITECTURE.md`.
- Sister-pack specifications at `docs/sister-packs/`.

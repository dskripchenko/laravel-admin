---
title: Migration Guide
audience: developer
status: stable
locale: en
---

# Migration Guide

Upgrades between major and minor versions. We follow [SemVer](https://semver.org).

## 1.4.x → 1.36.x

There is no 2.0 yet, and none of these releases requires a rewrite, but a
few change behaviour a host may rely on. Newest first:

- **1.75.1** — security fixes that change behaviour:
  - **Import.** Every `import/*` step requires `Resource::importable() = true`
    (else `403 import_disabled`) and the resource's `{permission}.create`.
    Columns map onto the create form's fields only (`422 invalid_mapping`),
    rows are written through validation and `fillModel()` rather than a
    `forceFill` of anything. The disk is `admin.imports.disk` only (a
    different `disk` in the request — `422 forbidden_disk`); the file must
    come from the user's own upload folder, so files uploaded before the
    upgrade (`imports/<name>`) have to be uploaded again. `import/status` —
    to the owner or a holder of `{permission}.create`. A resource that needs
    the import returns `true` from `importable()`.
  - **`uploads/serve`.** A disk listed by name in
    `admin.uploads.servable_disks` serves `admin.uploads.directory` only. If
    `serve` streamed other folders (media for `AudioEntry`, `VideoEntry`,
    `MediaUrl`), list them:
    `'servable_disks' => ['local', 'recordings' => ['calls'], 'media' => '*']`.
    Paths with `..`, `.`, `\` or a leading `/` are refused.
  - **Impersonation.** A new migration,
    `add_impersonator_to_admin_audit_logs` (`impersonator_type`,
    `impersonator_id`): run `php artisan migrate`. The start and the end are
    logged as `impersonation.started` / `.stopped` (two `login` entries
    before); give them captions if your audit list maps events.
    `block_higher_powered` compares through `hasAccess()` and refuses a
    target whose model has no `getAllPermissions()`. A target who cannot use
    the panel or is switched off — `422 target_cannot_sign_in`. Leaving does
    not bring back an administrator switched off or with a password changed
    meanwhile.
  - **`delayed/status`** answers only the user who started the process
    through `delayed/run`, holders of the handler's allowlisted permissions
    or of `admin.delayed.status_permission`; anyone else gets a 404. Mark a
    process your own code starts: `ProcessOwnership::remember($uuid, $user)`.
  - **`auth/resetPassword`** no longer signs in a user with 2FA on
    (`payload.user` is `null`): they sign in through the login form and code.
  - **API docs** (`/api/admin/doc`, `/api/doc/{panel}`) — only for users
    signed in to the panel with `admin.api_docs.permission`
    (`admin.systems.api-docs` by default), and off by default in
    `production` (`ADMIN_API_DOCS_ENABLED=true` turns them on). Grant the key
    to the roles that need it, or set `ADMIN_API_DOCS_PERMISSION=` (empty —
    a sign-in is enough). The page reads its specs from
    `/api/admin/doc/specs/{version}.json` and no longer writes them to
    `public/openapi`; old files there can go.
- **1.74.0** — an empty list reads "No records" (was "Nothing here yet");
  the 403 route gets `?permission=…&section=…`; every request carries
  `X-Request-Id` and the API answers with it; an API 403 carries
  `payload.permission`; a screen's state carries `save_method`; leaving a
  changed resource form asks first (`features.warnOnUnsavedChanges`);
  `TabsLayout` renders inside a `div.admin-tabs-layout`; a single stat
  without a link shows its previous-period value too.
- **1.73.0** — a new package migration, `admin_navigation_states`
  (favourites, recent pages, folded menu groups): run `php artisan migrate`.
  `system/search` groups carry `recognized` and `priority`; a list's search
  answer carries `meta.revealable`; a `TableColumn` serializes `masked`.
- **1.36.0** — requires `dskripchenko/laravel-api` `^5.11`.
- **1.34.0** — requires `@dskripchenko/ui` `^1.4.0` (matters only for a
  custom build). Menu items with equal `order` keep the order they were
  added in instead of being sorted alphabetically. Console commands
  (`admin:user`, `admin:make-*`) speak English.
- **1.33.0** — `admin:install` publishes the prebuilt frontend to
  `public/vendor/admin`; Node is no longer needed unless you register your
  own Vue components. The default admin locale follows `config('app.locale')`
  unless `ADMIN_LOCALE` / `admin.ui.default_locale` is set (it used to be
  Russian) — set it explicitly if you relied on the old default.
- **1.32.0** — host API versions stitched next to the admin
  (`parent::getApiVersionList()`) no longer inherit the admin middleware
  stack. If a version of yours relied on `AdminLocale` or the session coming
  from it, declare them in that version's `getMethods()`.
- **1.27.0** — the profile tabs "API tokens" and "Sessions" are shown only
  when the host fills the corresponding slot.
- **1.17.0** — saved list views are opt-in: return `true` from
  `Resource::savedViews()` on the resources that use them; the
  `{slug}_views/*` routes are registered only for those.
- **1.16.0** — the `{slug}/exportCsv` action is gone; call
  `{slug}/export?format=csv`.
- **1.15.0** — resource actions are registered by capability: `tree` and
  `treeScreen` only for hierarchical resources, `restore` and `forceDelete`
  only with `SoftDeletes`, `replicate` and `reorder` only when
  `replicable()` / `reorderable()`. An unsupported action answers 404
  instead of 409/422.
- **1.7.0** — the support matrix is PHP 8.2–8.5 and Laravel 11/12/13;
  `dskripchenko/laravel-api` `^5.0`.
- **1.5.6** — on a view page without a custom `infolist()`, `Switcher`
  fields render as a Yes/No `IconEntry` instead of raw `true`/`false`.

Run `php artisan migrate` after upgrading: new tables and columns arrive as
migrations. For everything else see [`CHANGELOG.md`](../../CHANGELOG.md).

## 1.3.x → 1.4.0

**No breaking changes.** New features are opt-in. Notable additions:

### New: hierarchical menu (M1+M2)

```php
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
```

If you don't call `Admin::menu()`, the auto-fill behaviour from 1.2.x
is preserved.

### New: Custom Screens (P21+P22)

```php
class ContactScreen extends Screen { /* ... */ }

Admin::screen([ContactScreen::class]);
```

URL: `/admin/screens/contact`. See
[concepts/screens.md](concepts/screens.md).

### New: Widget polling and rowSpan

```php
StatsOverviewWidget::make()
    ->title('Live')
    ->refresh(30)        // poll every 30s
    ->rowSpan(2);        // 2 grid rows tall (1.4.0)
```

`refresh()` was already in 1.2.x but the frontend ignored it. Now it
triggers a `setInterval` on `/dashboard/widgets`.

### Frontend: WidgetRenderer prop filter

If you wrote a custom widget Vue component that accessed
`props.size` (in pixels), you'll need to add your own pixel-size prop —
the previous `size` prop was a grid-column-span (1..12), but ambiguity
between span and pixels is now resolved by stripping dashboard-meta
fields. See
[frontend-extension.md → Custom widget](frontend-extension.md#custom-widget).

## 1.2.x → 1.3.0

Released as the initial drop of the Custom Screens API. All 1.4.0
changes started here; we recommend upgrading directly to 1.4.0
(non-breaking).

## 1.1.x → 1.2.0

### New: Dashboard widgets

`Widget::class`, `DashboardScreen::class` were introduced. Sister-packs
weren't bumped — they continue to work at `^1.2.0`.

### New: 2FA TOTP

The `two_factor_secret`, `two_factor_recovery_codes` and
`two_factor_confirmed_at` columns are added to `admin_users` by a migration.
Run `php artisan migrate` after upgrade.

### Bell-notifications

`SystemController::me` now returns `unread_notifications_count`. If
you've overridden the `me` payload, merge in the new field.

## 1.0.x → 1.1.0

(Pre-stable, no published 1.0.x release. 1.1.0 was the first stable
publish.)

## Upgrade checklist (general)

For any minor/patch bump:

```bash
composer update dskripchenko/laravel-admin
php artisan migrate
php artisan admin:publish        # republish the prebuilt frontend (the composer hook does it too)
php artisan optimize:clear
```

With your own build (`admin:install --custom-build`) update the npm packages
and rebuild instead of `admin:publish`:

```bash
npm update @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

The package config is merged with yours one level deep: a new key inside a
block you published (say, inside `auth`) does not appear until you copy it
over — compare with `vendor/dskripchenko/laravel-admin/config/admin.php`
when a release mentions a config change.

For major bumps, additionally:

1. Read this guide top-to-bottom for the relevant section.
2. Skim `CHANGELOG.md` for breaking changes.
3. Run your full test suite before deploying.

## See also

- [`CHANGELOG.md`](../../CHANGELOG.md)
- [Architecture](architecture.md)
- [Adding the admin to an existing application → Upgrading](integration.md#8-upgrading-and-troubleshooting)

---
title: API Reference
audience: developer
status: stable
locale: en
---

# API Reference

The admin SPA talks to the backend via JSON over `/api/admin/...`. All
responses follow the `{success, payload}` envelope from
`dskripchenko/laravel-api`. The prefix follows `config('laravel-api.prefix')`:
with `api/v1` the admin API moves to `/api/v1/admin/...`.

## Envelope

```json
{
  "success": true,
  "payload": { ... }
}
```

Error:

```json
{
  "success": false,
  "payload": {
    "errorKey": "validation",
    "message": "...",
    "messages": { "field": ["..."] }
  }
}
```

HTTP status codes: 200 / 304 / 401 / 403 / 404 / 422 / 429 / 500.

OpenAPI 3.0 spec is auto-generated and served at `/api/admin/doc`
(rendered with Scalar UI) — see [OpenAPI spec](#openapi-spec).

## Endpoints

Base prefix: `/api/admin/`.

### system

| Method | Path | Returns |
|---|---|---|
| GET | `system/bootstrap` | Initial SPA payload (CSRF, locale, theme, brand, user, manifestVersion). Public. |
| GET | `system/manifest` | Full manifest. Auth-gated. ETag cached. |
| GET | `system/me` | Current admin user summary. |
| GET | `system/menu` | Sidebar tree (custom + auto). |
| GET | `system/search` | Global search (the ⌘K palette). |
| GET | `system/locales` | Available locales. Public. |
| POST | `system/setLocale` | Save user locale. Public. |
| GET | `system/permissions` | All registered permission groups. |
| GET | `system/plugins` | Loaded plugins. |
| GET | `system/status` | Status indicators for the top bar (not cached). |
| GET | `system/theme` | Current theme. Public. |
| POST | `system/setTheme` | Save user theme. Public. |

### auth

| Method | Path | |
|---|---|---|
| POST | `auth/login` | Email + password → session. With 2FA on, answers `success: false`, `errorKey: two_factor_required` and a `challenge_token` (valid 5 minutes) instead. |
| POST | `auth/twoFactorChallenge` | `challenge_token` + TOTP code. |
| POST | `auth/twoFactorRecovery` | Recovery code. |
| POST | `auth/logout` | |
| POST | `auth/forgotPassword` | |
| POST | `auth/resetPassword` | |
| POST | `auth/verifyEmail` | |
| POST | `auth/resendEmailVerification` | |
| POST | `auth/startImpersonation` | Admin impersonates another user. |
| POST | `auth/stopImpersonation` | |

### profile

| Method | Path | |
|---|---|---|
| GET | `profile/show` | |
| POST | `profile/update` | |
| POST | `profile/changePassword` | |
| GET | `profile/twoFactorStatus` | `enabled`, `confirmed_at`, `recovery_codes_remaining`. |
| POST | `profile/twoFactorEnable` | New `secret`, provisioning `qr_uri` and `recovery_codes`; pending until confirmed. |
| POST | `profile/twoFactorConfirm` | Verify the first code. |
| POST | `profile/twoFactorDisable` | |
| POST | `profile/twoFactorRegenerateCodes` | |
| GET | `profile/tokensList` | (Sanctum) |
| POST | `profile/tokenCreate` | |
| POST | `profile/tokenRevoke` | |

### dashboard

| Method | Path | |
|---|---|---|
| GET | `dashboard/get?key={slug}` | User-saved layout (or null). |
| POST | `dashboard/save` | Save user layout. |
| POST | `dashboard/savePeriod` | Save the user's period filter without touching the layout. |
| POST | `dashboard/reset` | Delete user override (revert to manifest). |
| GET | `dashboard/widgets?key={slug}&period={p}` | Re-fetch widget data (used for polling and period change). |

### resources (per-Resource, dynamic)

For each registered Resource, prefix is `{slug}/`:

| Method | Path | |
|---|---|---|
| GET | `{slug}/meta` | Resource meta (fields, columns, filters, actions, screens). |
| POST | `{slug}/search` | Filtered, sorted, paginated list. Body: `{filters, q, order: [{column, direction}], page, per_page, ids?, group_by?}`. |
| POST | `{slug}/summary` | Aggregations for list (sum/avg/count). |
| GET | `{slug}/read?id={id}` | Single record. |
| POST | `{slug}/create` | |
| POST | `{slug}/update` | |
| POST | `{slug}/inlineUpdate` | Single field patch. |
| POST | `{slug}/delete` | |
| POST | `{slug}/restore` | Only for resources with `SoftDeletes`. |
| POST | `{slug}/forceDelete` | Only for resources with `SoftDeletes`. |
| POST | `{slug}/replicate` | Only when `replicable()`. |
| POST | `{slug}/reorder` | Only when `reorderable()`. |
| GET/POST | `{slug}/export?format=csv` | `format`: `csv` (default), `xlsx`, `pdf` — whichever exporters are installed; `columns[]` narrows the columns. |
| POST | `{slug}/action` | Generic action dispatcher. Body: `{key, ids[], payload}`. |
| GET | `{slug}/listScreen` | Compiled `GeneratedListScreen` snapshot. |
| GET | `{slug}/treeScreen` | Only for hierarchical resources. |
| POST | `{slug}/tree` | Tree nodes (hierarchical resources). |
| GET | `{slug}/createScreen` | |
| GET | `{slug}/editScreen?id={id}` | |
| GET | `{slug}/viewScreen?id={id}` | |
| POST | `{slug}/listener` | Reactive form parts (`Layout::listener`); only when the form has any. |

An action the resource does not support is not registered and answers 404.

Saved-views: `{slug}_views/{list,create,update,delete}` — registered only for resources whose `savedViews()` returns `true`.

### screens (per-Screen, dynamic)

For each registered Custom Screen, prefix is `{slug}/`:

| Method | Path | |
|---|---|---|
| GET | `{slug}/state` | `Screen::compile()` payload. |
| POST | `{slug}/runMethod` | Body: `{method, payload, parameters?}`. |
| POST | `{slug}/listener` | Reactive form parts. |

### settings (per-SettingsResource, dynamic)

Prefix `settings_{slug}/`:

| Method | Path | |
|---|---|---|
| GET | `settings_{slug}/meta` | |
| GET | `settings_{slug}/read` | |
| POST | `settings_{slug}/update` | |

### audit

| Method | Path | |
|---|---|---|
| GET | `audit/list` | All audit log entries (filters: `subject_type`, `subject_id`, `actor_type`, `actor_id`, `event`, `from`, `to`). |
| GET | `audit/timeline?subject_type=&subject_id=` | Per-record timeline. |

### notifications

| Method | Path | |
|---|---|---|
| GET | `notifications/list?type=all|unread|read` | |
| GET | `notifications/unread` | For bell-badge polling. |
| POST | `notifications/markAsRead` | |
| POST | `notifications/markAllAsRead` | |
| POST | `notifications/destroy` | |

### import

| Method | Path | |
|---|---|---|
| POST | `import/upload` | Stage CSV/XLSX. |
| POST | `import/preview` | Headers + sample + auto-mapping. |
| POST | `import/start` | Run import. |
| GET | `import/status?id={id}` | Progress of the import process. |

### uploads

| Method | Path | |
|---|---|---|
| POST | `uploads/upload` | Generic file upload. |
| POST | `uploads/image` | Image-specific (used by Wysiwyg). |
| GET | `uploads/serve?disk=&path=` | Serve a stored file. |

### delayed (long-running tasks)

| Method | Path | |
|---|---|---|
| POST | `delayed/run` | Start a queued process. Allowlisted handlers only. |
| GET | `delayed/status?uuid={u}` | Poll progress. |

## Caching

- **Manifest** — ETag = the manifest `version` (sha256 of the package
  version and the payload, which is built per locale and panel, so it also
  changes with permissions). Use `If-None-Match` to get 304.
- **Bootstrap** — not cached (per-request CSRF).
- **Other endpoints** — no HTTP cache (auth-gated, fast queries).

## Rate limiting

Default `240/min` on all admin endpoints (`config('admin.api.throttle')`,
`'240,1'`), additional throttles on auth:

- `auth/login`, `auth/twoFactorChallenge`, `auth/twoFactorRecovery` — 5/min
  (`config('admin.auth.login_throttle')`, `'5,1'`)
- `auth/forgotPassword` — 3/5min
- `auth/resendEmailVerification` — 3/min

## OpenAPI spec

```
GET /api/admin/doc            # Scalar UI (interactive)
GET /api/doc/admin            # Raw JSON spec (laravel-api's per-version source)
```

The Scalar page is on by default; `config('admin.openapi.ui')` (env
`ADMIN_OPENAPI_UI`) set to anything but `scalar` leaves laravel-api's own
`/api/doc` in charge.

Resource operations are documented per resource. `create` and `update` list
the resource's own fields — built from `fields()` and `validationRules()`, with
types, formats, required, enums from options, and bounds — and `search`,
`export`, `action`, `reorder`, `inlineUpdate` and a settings group's `update`
document the parts that depend on the resource (filters, sortable and
exportable columns, action keys, editable columns, settings values). A
controller that serves many routes can do the same: declare
`@input [method]` and type-hint `Dskripchenko\LaravelApi\Services\OpenApi\OperationContext`
in that method — it is told the controller key and action of the route being
described.

`php artisan api:lint --strict` checks the markup, including actions that
validate input without declaring any (`input.undeclared`).

## See also

- [Architecture](architecture.md) — manifest + envelope shape
- [Frontend extension](frontend-extension.md) — adding custom endpoints

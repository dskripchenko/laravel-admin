---
title: API Reference
audience: developer
status: stable
locale: en
---

# API Reference

The admin SPA talks to the backend via JSON over `/api/admin/...`. All
responses follow the `{success, payload}` envelope from
`dskripchenko/laravel-api`.

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
    "errorKey": "validation_error",
    "message": "...",
    "messages": { "field": ["..."] }
  }
}
```

HTTP status codes: 200 / 401 / 403 / 404 / 422 / 500.

OpenAPI 3.0 spec is auto-generated and served at `/api/admin/doc`
(rendered with Scalar UI).

## Endpoints

Base prefix: `/api/admin/`.

### system

| Method | Path | Returns |
|---|---|---|
| GET | `system/bootstrap` | Initial SPA payload (CSRF, locale, theme, brand, user, manifestVersion). Public. |
| GET | `system/manifest` | Full manifest. Auth-gated. ETag cached. |
| GET | `system/me` | Current admin user summary. |
| GET | `system/menu` | Sidebar tree (custom + auto). |
| GET | `system/locales` | Available locales. Public. |
| POST | `system/setLocale` | Save user locale. |
| GET | `system/permissions` | All registered permission groups. |
| GET | `system/plugins` | Loaded plugins. |
| GET | `system/theme` | Current theme. Public. |
| POST | `system/setTheme` | Save user theme. |

### auth

| Method | Path | |
|---|---|---|
| POST | `auth/login` | Email + password → session. May return 423 with `errorKey: two_factor_required`. |
| POST | `auth/twoFactorChallenge` | TOTP code. |
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
| GET | `profile/twoFactorStatus` | Returns enabled/qr/secret. |
| POST | `profile/twoFactorEnable` | Returns provisioning URI + recovery codes. |
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
| POST | `dashboard/reset` | Delete user override (revert to manifest). |
| GET | `dashboard/widgets?key={slug}&period={p}` | Re-fetch widget data (used for polling and period change). |

### resources (per-Resource, dynamic)

For each registered Resource, prefix is `{slug}/`:

| Method | Path | |
|---|---|---|
| GET | `{slug}/meta` | Resource meta (fields, columns, filters, actions, screens). |
| POST | `{slug}/search` | Filtered, sorted, paginated list. Body: `{filters, sort, page, per_page, ...}`. |
| POST | `{slug}/summary` | Aggregations for list (sum/avg/count). |
| GET | `{slug}/read?id={id}` | Single record. |
| POST | `{slug}/create` | |
| POST | `{slug}/update` | |
| POST | `{slug}/inlineUpdate` | Single field patch. |
| POST | `{slug}/delete` | |
| POST | `{slug}/restore` | (soft-delete) |
| POST | `{slug}/forceDelete` | |
| POST | `{slug}/replicate` | |
| POST | `{slug}/reorder` | |
| POST | `{slug}/exportCsv` | |
| POST | `{slug}/export?format=xlsx&pdf...` | |
| POST | `{slug}/action` | Generic action dispatcher. Body: `{key, ids[], payload}`. |
| GET | `{slug}/listScreen` | Compiled `GeneratedListScreen` snapshot. |
| GET | `{slug}/createScreen` | |
| GET | `{slug}/editScreen?id={id}` | |
| GET | `{slug}/viewScreen?id={id}` | |

Saved-views: `{slug}_views/{list,create,update,delete}`.

### screens (per-Screen, dynamic)

For each registered Custom Screen, prefix is `{slug}/`:

| Method | Path | |
|---|---|---|
| GET | `{slug}/state` | `Screen::compile()` payload. |
| POST | `{slug}/runMethod` | Body: `{method, payload, parameters?}`. |

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
| GET | `audit/list` | All audit log entries (filterable). |
| GET | `audit/timeline?model_type=&model_id=` | Per-record timeline. |

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
| GET | `import/status?id={uuid}` | Progress (delayed-process). |

### uploads

| Method | Path | |
|---|---|---|
| POST | `uploads/upload` | Generic file upload. |
| POST | `uploads/image` | Image-specific (used by Wysiwyg). |

### delayed (long-running tasks)

| Method | Path | |
|---|---|---|
| POST | `delayed/run` | Start a queued process. Allowlisted handlers only. |
| GET | `delayed/status?uuid={u}` | Poll progress. |

## Caching

- **Manifest** — ETag (sha256 of payload + version + locale +
  permissions). Use `If-None-Match` to get 304.
- **Bootstrap** — not cached (per-request CSRF).
- **Other endpoints** — no HTTP cache (auth-gated, fast queries).

## Rate limiting

Default `60/min` on all admin endpoints, additional throttles on auth:

- `auth/login` — 5/min
- `auth/forgotPassword` — 3/5min
- `auth/twoFactorChallenge` — 5/min

## OpenAPI spec

```
GET /api/admin/doc            # Scalar UI (interactive)
GET /api/admin/openapi.json   # Raw spec
```

## See also

- [Architecture](architecture.md) — manifest + envelope shape
- [Frontend extension](frontend-extension.md) — adding custom endpoints

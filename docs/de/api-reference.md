---
title: API-Referenz
audience: developer
status: stable
locale: de
translated_from: en/api-reference.md
translated_at: 2026-10-02
---

# API-Referenz

Die Admin-SPA kommuniziert mit dem Backend per JSON über `/api/admin/...`. Alle
Responses folgen dem `{success, payload}`-Envelope aus
`dskripchenko/laravel-api`. Das Präfix richtet sich nach `config('laravel-api.prefix')`:
mit `api/v1` wandert die Admin-API nach `/api/v1/admin/...`.

## Envelope

```json
{
  "success": true,
  "payload": { ... }
}
```

Fehler:

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

HTTP-Statuscodes: 200 / 304 / 401 / 403 / 404 / 422 / 429 / 500.

Die OpenAPI-3.0-Spezifikation wird automatisch generiert und unter `/api/admin/doc`
ausgeliefert (dargestellt mit Scalar UI) — siehe [OpenAPI-Spezifikation](#openapi-spezifikation).

## Endpoints

Basispräfix: `/api/admin/`.

### system

| Methode | Pfad | Liefert |
|---|---|---|
| GET | `system/bootstrap` | Initiale SPA-Payload (CSRF, Locale, Theme, Branding, Benutzer, manifestVersion). Öffentlich. |
| GET | `system/manifest` | Vollständiges Manifest. Erfordert Authentifizierung. Per ETag gecacht. |
| GET | `system/me` | Übersicht über den aktuellen Admin-Benutzer. |
| GET | `system/menu` | Sidebar-Baum (eigene + automatische Einträge). |
| GET | `system/search` | Globale Suche (die ⌘K-Palette). |
| GET | `system/locales` | Verfügbare Locales. Öffentlich. |
| POST | `system/setLocale` | Speichert die Locale des Benutzers. Öffentlich. |
| GET | `system/permissions` | Alle registrierten Berechtigungsgruppen. |
| GET | `system/plugins` | Geladene Plugins. |
| GET | `system/status` | Statusindikatoren für die obere Leiste (nicht gecacht). |
| GET | `system/theme` | Aktuelles Theme. Öffentlich. |
| POST | `system/setTheme` | Speichert das Theme des Benutzers. Öffentlich. |

### auth

| Methode | Pfad | |
|---|---|---|
| POST | `auth/login` | E-Mail + Passwort → Session. Bei aktivierter 2FA antwortet der Endpoint stattdessen mit `success: false`, `errorKey: two_factor_required` und einem `challenge_token` (5 Minuten gültig). |
| POST | `auth/twoFactorChallenge` | `challenge_token` + TOTP-Code. |
| POST | `auth/twoFactorRecovery` | Wiederherstellungscode. |
| POST | `auth/logout` | |
| POST | `auth/forgotPassword` | |
| POST | `auth/resetPassword` | |
| POST | `auth/verifyEmail` | |
| POST | `auth/resendEmailVerification` | |
| POST | `auth/startImpersonation` | Ein Admin agiert als ein anderer Benutzer. |
| POST | `auth/stopImpersonation` | |

### profile

| Methode | Pfad | |
|---|---|---|
| GET | `profile/show` | |
| POST | `profile/update` | |
| POST | `profile/changePassword` | |
| GET | `profile/twoFactorStatus` | `enabled`, `confirmed_at`, `recovery_codes_remaining`. |
| POST | `profile/twoFactorEnable` | Neues `secret`, Provisioning-`qr_uri` und `recovery_codes`; ausstehend bis zur Bestätigung. |
| POST | `profile/twoFactorConfirm` | Prüft den ersten Code. |
| POST | `profile/twoFactorDisable` | |
| POST | `profile/twoFactorRegenerateCodes` | |
| GET | `profile/tokensList` | (Sanctum) |
| POST | `profile/tokenCreate` | |
| POST | `profile/tokenRevoke` | |

### dashboard

| Methode | Pfad | |
|---|---|---|
| GET | `dashboard/get?key={slug}` | Vom Benutzer gespeichertes Layout (oder null). |
| POST | `dashboard/save` | Speichert das Benutzer-Layout. |
| POST | `dashboard/savePeriod` | Speichert den Zeitraumfilter des Benutzers, ohne das Layout anzutasten. |
| POST | `dashboard/reset` | Löscht die Benutzer-Überschreibung (Rückkehr zum Manifest). |
| GET | `dashboard/widgets?key={slug}&period={p}` | Lädt Widget-Daten erneut (für Polling und Zeitraumwechsel). |

### resources (pro Resource, dynamisch)

Für jede registrierte Resource lautet das Präfix `{slug}/`:

| Methode | Pfad | |
|---|---|---|
| GET | `{slug}/meta` | Metadaten der Resource (Felder, Spalten, Filter, Actions, Screens). |
| POST | `{slug}/search` | Gefilterte, sortierte, paginierte Liste. Body: `{filters, q, order: [{column, direction}], page, per_page, ids?, group_by?}`. |
| POST | `{slug}/summary` | Aggregationen für die Liste (sum/avg/count). |
| GET | `{slug}/read?id={id}` | Einzelner Datensatz. |
| POST | `{slug}/create` | |
| POST | `{slug}/update` | |
| POST | `{slug}/inlineUpdate` | Patch eines einzelnen Feldes. |
| POST | `{slug}/delete` | |
| POST | `{slug}/restore` | Nur für Resources mit `SoftDeletes`. |
| POST | `{slug}/forceDelete` | Nur für Resources mit `SoftDeletes`. |
| POST | `{slug}/replicate` | Nur bei `replicable()`. |
| POST | `{slug}/reorder` | Nur bei `reorderable()`. |
| GET/POST | `{slug}/export?format=csv` | `format`: `csv` (Standard), `xlsx`, `pdf` — je nachdem, welche Exporter installiert sind; `columns[]` schränkt die Spalten ein. |
| POST | `{slug}/action` | Generischer Action-Dispatcher. Body: `{key, ids[], payload}`. |
| GET | `{slug}/listScreen` | Kompilierter Snapshot von `GeneratedListScreen`. |
| GET | `{slug}/treeScreen` | Nur für hierarchische Resources. |
| POST | `{slug}/tree` | Baumknoten (hierarchische Resources). |
| GET | `{slug}/createScreen` | |
| GET | `{slug}/editScreen?id={id}` | |
| GET | `{slug}/viewScreen?id={id}` | |
| POST | `{slug}/listener` | Reaktive Formularteile (`Layout::listener`); nur wenn das Formular welche enthält. |

Eine Action, die die Resource nicht unterstützt, wird nicht registriert und antwortet mit 404.

Gespeicherte Ansichten: `{slug}_views/{list,create,update,delete}` — nur für Resources registriert, deren `savedViews()` `true` zurückgibt.

### screens (pro Screen, dynamisch)

Für jeden registrierten Custom Screen lautet das Präfix `{slug}/`:

| Methode | Pfad | |
|---|---|---|
| GET | `{slug}/state` | Payload von `Screen::compile()`. |
| POST | `{slug}/runMethod` | Body: `{method, payload, parameters?}`. |
| POST | `{slug}/listener` | Reaktive Formularteile. |

### settings (pro SettingsResource, dynamisch)

Präfix `settings_{slug}/`:

| Methode | Pfad | |
|---|---|---|
| GET | `settings_{slug}/meta` | |
| GET | `settings_{slug}/read` | |
| POST | `settings_{slug}/update` | |

### audit

| Methode | Pfad | |
|---|---|---|
| GET | `audit/list` | Alle Einträge des Audit-Logs (Filter: `subject_type`, `subject_id`, `actor_type`, `actor_id`, `event`, `from`, `to`). |
| GET | `audit/timeline?subject_type=&subject_id=` | Zeitleiste pro Datensatz. |

### notifications

| Methode | Pfad | |
|---|---|---|
| GET | `notifications/list?type=all|unread|read` | |
| GET | `notifications/unread` | Für das Polling des Glocken-Badges. |
| POST | `notifications/markAsRead` | |
| POST | `notifications/markAllAsRead` | |
| POST | `notifications/destroy` | |

### import

| Methode | Pfad | |
|---|---|---|
| POST | `import/upload` | CSV/XLSX bereitstellen. |
| POST | `import/preview` | Kopfzeilen + Stichprobe + automatisches Mapping. |
| POST | `import/start` | Import ausführen. |
| GET | `import/status?id={id}` | Fortschritt des Importprozesses. |

### uploads

| Methode | Pfad | |
|---|---|---|
| POST | `uploads/upload` | Generischer Datei-Upload. |
| POST | `uploads/image` | Speziell für Bilder (von Wysiwyg verwendet). |
| GET | `uploads/serve?disk=&path=` | Liefert eine gespeicherte Datei aus. |

### delayed (lang laufende Aufgaben)

| Methode | Pfad | |
|---|---|---|
| POST | `delayed/run` | Startet einen Prozess in der Queue. Nur Handler aus der Allowlist. |
| GET | `delayed/status?uuid={u}` | Fortschritt abfragen. |

## Caching

- **Manifest** — ETag = die `version` des Manifests (sha256 aus der Paketversion
  und der Payload, die pro Locale und Panel erzeugt wird und sich daher auch
  mit den Berechtigungen ändert). Verwenden Sie `If-None-Match`, um 304 zu erhalten.
- **Bootstrap** — nicht gecacht (CSRF pro Request).
- **Übrige Endpoints** — kein HTTP-Cache (erfordern Authentifizierung, schnelle Abfragen).

## Rate Limiting

Standardmäßig `240/min` auf allen Admin-Endpoints (`config('admin.api.throttle')`,
`'240,1'`), zusätzliche Limits für auth:

- `auth/login`, `auth/twoFactorChallenge`, `auth/twoFactorRecovery` — 5/min
  (`config('admin.auth.login_throttle')`, `'5,1'`)
- `auth/forgotPassword` — 3/5min
- `auth/resendEmailVerification` — 3/min

## OpenAPI-Spezifikation

```
GET /api/admin/doc            # Scalar UI (interaktiv)
GET /api/doc/admin            # Rohe JSON-Spezifikation (Quelle pro Version von laravel-api)
```

Die Scalar-Seite ist standardmäßig aktiv; wird `config('admin.openapi.ui')` (env
`ADMIN_OPENAPI_UI`) auf einen anderen Wert als `scalar` gesetzt, bleibt das
eigene `/api/doc` von laravel-api zuständig.

Die Operationen von Resources werden pro Resource dokumentiert. `create` und `update` listen
die eigenen Felder der Resource auf — erzeugt aus `fields()` und `validationRules()`, mit
Typen, Formaten, Pflichtangaben, Enums aus den Optionen und Grenzwerten — und `search`,
`export`, `action`, `reorder`, `inlineUpdate` sowie das `update` einer Settings-Gruppe
dokumentieren die Teile, die von der Resource abhängen (Filter, sortierbare und
exportierbare Spalten, Action-Schlüssel, editierbare Spalten, Settings-Werte). Ein
Controller, der viele Routen bedient, kann dasselbe tun: Deklarieren Sie
`@input [method]` und geben Sie in dieser Methode den Typ-Hint `Dskripchenko\LaravelApi\Services\OpenApi\OperationContext`
an — er erhält den Controller-Schlüssel und die Action der beschriebenen Route.

`php artisan api:lint --strict` prüft das Markup, einschließlich Actions, die
Eingaben validieren, ohne welche zu deklarieren (`input.undeclared`).

## Siehe auch

- [Architektur](architecture.md) — Aufbau von Manifest + Envelope
- [Frontend-Erweiterung](frontend-extension.md) — eigene Endpoints hinzufügen

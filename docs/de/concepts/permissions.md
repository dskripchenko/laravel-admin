---
title: Berechtigungen
audience: developer
status: stable
locale: de
translated_from: en/concepts/permissions.md
translated_at: 2026-10-02
---

# Berechtigungen

RBAC: Benutzer haben Rollen, Rollen enthalten Berechtigungs-Strings, und jede
Aktion wird durch die Middleware `AdminAccess` geschützt.

## Berechtigungsschlüssel

Format: `admin.{domain}.{action}`. Beispiele:

- `admin.users.view`
- `admin.articles.update`
- `admin.system.settings.edit`
- `admin.audit.view`

Platzhalter:
- `admin.users.*` — alle Aktionen in der Domäne users
- `admin.*.view` — Lesezugriff auf alles
- `admin.*` — alle Admin-Berechtigungen
- `*` — Superadmin

Ein vergebener Schlüssel mit `*` wird per `fnmatch()` abgeglichen, wobei `*` auch
Punkte erfasst: `admin.*` deckt ebenso `admin.cms.articles.view` ab.

## Rollen

Gespeichert in `admin_roles`; `permissions` ist eine JSON-Liste von Strings. Ein
Benutzermodell mit dem Trait `HasAdminAccess` erhält eine Rolle über
`assignRole()` (eine `Role`, ihre ID oder ihren Slug) und verliert sie über
`revokeRole()`:

```php
$role = Role::create([
    'name' => 'Redakteur', 'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($role);
```

## Automatische Berechtigungen für Resources

Für jede Resource leitet die Administration Berechtigungsschlüssel aus
`Resource::permission()` ab (standardmäßig `admin.{slug}`) und schützt die Routen
der Resource damit:

```
admin.articles.view
admin.articles.create
admin.articles.update
admin.articles.delete
admin.articles.restore         (if soft-delete)
admin.articles.force-delete    (if soft-delete)
admin.articles.replicate       (if replicable)
admin.articles.reorder         (if reorderable)
```

Die Middleware `AdminAccess:admin.articles.create` schützt die `create`-Route
automatisch. Eigene Actions (`POST /{slug}/action`) erfordern `.view`. Diese
Schlüssel sind nur Prüfungen: Um sie im Rolleneditor ankreuzbar zu machen,
registrieren Sie sie mit `Admin::permissions()` (siehe unten).

Überschreiben Sie die Basis über `Resource::permission()`:

```php
public static function permission(): string
{
    return 'admin.cms.articles';   // → admin.cms.articles.view, .update, ...
}
```

## Registrierung eigener Berechtigungen

```php
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;

Admin::permissions(
    ItemPermission::group('Berichte')
        ->addPermission('admin.reports.view', 'Berichte ansehen')
        ->addPermission('admin.reports.export', 'Berichte exportieren'),
);
```

Diese erscheinen im Bearbeitungs-Screen für Rollen als ankreuzbare Einträge.

Übergeben Sie den Gruppennamen und die Beschriftungen als Quellzeichenketten (oder
Übersetzungsschlüssel), nicht als Ergebnisse von `__()`: Gruppen werden einmal beim
Boot registriert, und die Beschriftungen werden bei der Auslieferung über die
JSON-Übersetzungen in der Locale des jeweiligen Requests übersetzt. Eine bereits
übersetzte Beschriftung wird unverändert zurückgegeben.

## Prüfung im Code

```php
$user->hasAccess('admin.articles.update');   // true / false
$user->hasAnyAccess(['admin.articles.update', 'admin.articles.delete']);  // OR
$user->hasAllAccess(['admin.articles.update', 'admin.articles.delete']);  // AND
```

In der SPA (der Bootstrap-Payload und die Login-Response enthalten die flache
Berechtigungsliste des Benutzers; der Store gleicht dieselben `*`-Masken ab):

```ts
const auth = useAuthStore()
auth.hasAnyPermission(['admin.articles.update'])
```

## Middleware auf Aktionen

Die Middleware `AdminAccess` akzeptiert eine oder mehrere durch `;` getrennte
Berechtigungen:

```php
'middleware' => [AdminAccess::class.':admin.users.view'],
'middleware' => [AdminAccess::class.':admin.users.view;admin.users.update'],  // AND
```

Für eine ODER-Semantik über mehrere Berechtigungen — prüfen Sie explizit innerhalb
des Action-Handlers.

## Screens

Ein Screen deklariert seine Prüfung über `permission()`:

```php
public function permission(): array|string|null
{
    return ['admin.reports.view', 'admin.exports.run'];   // AND
}
```

Die Endpoints `state` (GET), `runMethod` und `listener` (POST) werden automatisch
geschützt. `null` (der Standard) bedeutet, dass die Authentifizierung allein
genügt.

## Settings

Jede `SettingsResource` erhält `admin.settings.{slug}.view` /
`admin.settings.{slug}.update`.

## 2FA

Unabhängig von RBAC. Ist sie für einen Benutzer aktiviert, verlangt der
Anmeldevorgang unabhängig von der Rolle den TOTP-Code.

## Siehe auch

- [Resources](resources.md) — automatische Berechtigungen pro CRUD-Aktion
- [Screens](screens.md) — Methode `permission()`
- [Mandantenfähigkeit](tenancy.md) — mandantenbezogene Daten + Berechtigungen

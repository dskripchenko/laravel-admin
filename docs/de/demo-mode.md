---
title: Demo-Modus
audience: developer
status: stable
locale: de
translated_from: en/demo-mode.md
translated_at: 2026-10-02
---

# Demo-Modus

Der Demo-Modus macht aus einer Installation einen öffentlichen
Demonstrationsstand: Besucher melden sich mit einem Klick als eines der
Demo-Konten an, und Operationen, mit denen ein Besucher den Stand für den
nächsten verderben könnte, werden abgelehnt. Standardmäßig ist er
ausgeschaltet.

```dotenv
ADMIN_DEMO=true
```

## Demo-Konten

Führen Sie die Konten in `config/admin.php` auf; die Login-Seite zeigt für
jedes davon eine Schaltfläche „Anmelden als …“:

```php
'demo' => [
    'enabled' => (bool) env('ADMIN_DEMO', false),
    'accounts' => [
        [
            'label' => 'Administrator',
            'email' => 'admin@demo.test',
            'password' => 'demo',
            'description' => 'Vollzugriff',
        ],
        [
            'label' => 'Redakteur',
            'email' => 'editor@demo.test',
            'password' => 'demo',
            'description' => 'Nur Inhalte, keine Einstellungen',
        ],
    ],
],
```

Ein Klick füllt das Formular aus und meldet sich über den gewöhnlichen
Login-Endpoint an, sodass alles, was für einen Login gilt — das Throttling,
ein deaktiviertes Konto, 2FA —, auch hier gilt. `label` und `description`
laufen durch den Übersetzer und dürfen daher Übersetzungsschlüssel sein.

> **Warnung** Die Passwörter werden an jeden Besucher der Login-Seite
> gesendet. Führen Sie ausschließlich Demo-Konten auf, niemals ein echtes, und
> legen Sie sie mit den Berechtigungen an, die Sie gern zeigen möchten.

Die Konten selbst sind gewöhnliche Benutzer: Legen Sie sie in einem Seeder an.

## Schreibschutz

Mit eingeschaltetem `readonly` (der Standard, sobald der Demo-Modus aktiviert
ist) lehnt die API eine Reihe von Operationen mit `403` und
`errorKey: demo_readonly` ab. Das Panel zeigt die Ablehnung als Toast an —
„Demo-Modus: Diese Aktion ist deaktiviert.“ — statt eines allgemeinen Fehlers.

```php
'demo' => [
    'readonly' => (bool) env('ADMIN_DEMO_READONLY', true),

    // API-Actions als `controller.action`-Muster; `*` passt auf alles.
    'blocked' => [
        'profile.update',
        'profile.changePassword',
        'profile.twoFactorEnable',
        'profile.twoFactorConfirm',
        'profile.twoFactorDisable',
        'profile.twoFactorRegenerateCodes',
        'profile.tokenCreate',
        'profile.tokenRevoke',
        'auth.startImpersonation',
        'import.*',
        'settings_*.update',
    ],

    // Schreibzugriffe auf die Resources dieser Modelle werden abgelehnt. null:
    // das Benutzermodell des Panels und Role — niemand kann den nächsten
    // Besucher aussperren.
    'protected_models' => null,
    'protected_actions' => [
        'create', 'update', 'inlineUpdate', 'replicate', 'reorder',
        'delete', 'restore', 'forceDelete', 'action',
    ],

    // Hochgeladene Dateien über dieser Größe werden abgelehnt; 0 schaltet die Grenze ab.
    'max_upload_kb' => 2048,
],
```

Was die Standardwerte abdecken:

| Operation | Warum |
|---|---|
| Ändern des Profils und des Passworts | Der nächste Besucher könnte sich nicht anmelden |
| Aktivieren oder Deaktivieren von 2FA, Neuerzeugen der Wiederherstellungscodes | Ebenso |
| Erstellen und Widerrufen von API-Tokens | Ein Token überdauert die Demo-Sitzung |
| Impersonation | Öffnet jedes Konto des Stands |
| Schreibzugriffe auf Benutzer und Rollen | Löschen der Demo-Konten oder Entzug ihrer Berechtigungen |
| Settings | Settings werden von allen Besuchern geteilt |
| Import | Massenhafte Schreibzugriffe |
| Große Uploads | Speicherplatz |

Alles andere — Anlegen, Bearbeiten und Löschen Ihrer Demo-Datensätze — bleibt
offen; genau das möchte ein Besucher ausprobieren.

### Die Listen anpassen

Die Listen sind einfache Konfiguration, sodass ein Host Einträge hinzufügen
oder entfernen kann:

```php
// Das Profil erlauben, zusätzlich die Bulk-Actions von "articles" ablehnen.
'blocked' => [
    'profile.changePassword',
    'articles.action',
    'settings_*.update',
],

// Ein weiteres Modell schützen.
'protected_models' => [
    App\Models\User::class,
    Dskripchenko\LaravelAdmin\Permission\Models\Role::class,
    App\Models\Tenant::class,
],
```

Der Controller einer Resource ist ihr Slug (`articles.update`), der einer
Settings-Seite `settings_{slug}`, der eines Screens sein Slug
(`reports.runMethod`). Eingebaute Controller: `auth`, `profile`, `system`,
`dashboard`, `audit`, `import`, `uploads`, `notifications`, `delayed`.

`readonly` wird pro Request geprüft und lässt sich daher auch zur Laufzeit
umschalten — in einer Middleware, für einen bestimmten Benutzer:

```php
config(['admin.demo.readonly' => ! $request->user()?->is_staff]);
```

## Das Banner

Teilen Sie den Besuchern mit dem Installationsbanner `admin.notice` mit, auf
was für einem Stand sie sich befinden. Es wird von der Shell gezeichnet,
oberhalb des Panels und auf der Login-Seite:

```dotenv
ADMIN_NOTICE="Demo-Stand: Die Daten werden stündlich zurückgesetzt"
ADMIN_NOTICE_HREF=https://example.com/docs
ADMIN_NOTICE_COUNTDOWN_LABEL="bis zum Zurücksetzen"
```

`countdown_to` (ISO-8601) fügt einen Countdown hinzu; setzen Sie es in einer
Middleware, da eine gecachte Konfiguration einen berechneten Wert einfrieren
würde:

```php
config(['admin.notice.countdown_to' => now()->startOfHour()->addHour()->toIso8601String()]);
```

## Die Daten zurücksetzen

Das Paket setzt den Stand nicht selbst zurück: Planen Sie einen eigenen
Befehl, der die Datenbank wiederherstellt (`migrate:fresh --seed`, ein Dump,
ein Snapshot) und die Demo-Konten neu anlegt.

```php
// routes/console.php
Schedule::command('migrate:fresh --seed --force')->hourly();
```

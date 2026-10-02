---
title: Das Admin-Panel zu einer bestehenden Laravel-Anwendung hinzufügen
audience: developer
status: stable
locale: de
translated_from: en/integration.md
translated_at: 2026-10-02
---

# Das Admin-Panel zu einer bestehenden Laravel-Anwendung hinzufügen

[Erste Schritte](getting-started.md) führt eine frische Anwendung zu einem
funktionierenden Admin-Panel. Diese Seite richtet sich an eine Anwendung, die
bereits Benutzer, eine API, einen vorgeschalteten Proxy und eine
Deploy-Pipeline hat: was der Installer ändert, wie sich Ihre bestehenden
Benutzer anmelden und wie das Admin-Panel mit dem Rest koexistiert.

## 1. Voraussetzungen und was der Installer ändert

- PHP 8.2+, Laravel 11, 12 oder 13.
- Eine Datenbank, die die Anwendung bereits migriert. Das Admin-Panel hält
  seinen State in eigenen Tabellen und nutzt die Session von Laravel (die
  Middleware-Gruppe `web`).
- Node wird **nicht** benötigt, es sei denn, Sie bauen das Frontend selbst.

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install            # separate Tabelle admin_users (dedicated)
# oder
php artisan admin:install --shared   # Ihre bestehenden Benutzer melden sich an (shared)
```

`admin:install` fragt vor jedem Schritt nach, der etwas verändert; zu jeder
Frage gibt es ein Flag (`--no-migrate`, `--no-user`, `--no-composer-hook`,
`--force`), und mit `-n` läuft der Befehl unbeaufsichtigt mit den
Standardwerten.

Was er schreibt:

| Was | Wo |
|---|---|
| Konfiguration | `config/admin.php` |
| Migrationen | `database/migrations/2026_01_01_0000*_*.php` (Kopien der paketeigenen; Laravel führt jeden Namen nur einmal aus) |
| Vorgebautes Frontend | `public/vendor/admin/` (`assets/` + `source-hash.txt`) |
| Composer-Hook (optional) | `"@php artisan admin:publish --ansi"`, angehängt an `scripts.post-update-cmd` in `composer.json` |
| Nur `--shared` | der Block `auth` von `config/admin.php`, ausgerichtet auf Ihren Guard, sowie `database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php` |
| Nur `--custom-build` | `resources/js/admin.js`, ein Eintrag in den `laravel()`-Inputs von `vite.config.js`, `assets.vite_manifest`/`vite_entry` in `config/admin.php` |

Von `migrate` erstellte Tabellen: `admin_users`, `admin_password_resets`,
`admin_roles`, `admin_role_assignments`, `admin_saved_views`,
`admin_dashboard_layouts`, `admin_audit_logs`, `admin_settings`,
`admin_import_processes`. Zwei Abhängigkeiten bringen eigene Migrationen mit,
die ebenfalls laufen: `dskripchenko/laravel-delayed-process`
(`delayed_processes`) und `dskripchenko/laravel-translatable` (`languages`,
`translations`, `content_blocks`, `pages`, `page_content_block`). Die
Migrationen des Pakets werden aus `vendor/` geladen, auch wenn Sie die
veröffentlichten Kopien löschen.

Der Installer rührt `config/auth.php`, Ihre Modelle, Routen und `.env` nicht
an. In der Strategie dedicated werden der Guard `admin`, der Provider
`admin_users` und der Password-Broker `admin_users` **zur Laufzeit** zur
Auth-Konfiguration hinzugefügt, und zwar nur, wenn Sie selbst keine Guards
mit diesen Namen definiert haben.

### Rückgängig machen

```bash
# 1. Die Tabellen des Admin-Panels zurückrollen (und bei --shared die Spalten in users).
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-admin/database/migrations \
  --path=database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php
# Die Tabellen der Abhängigkeiten, sofern nichts anderes von Ihnen sie nutzt:
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-delayed-process/databases/migrations \
  --path=vendor/dskripchenko/laravel-translatable/databases/migrations

# 2. Die Dateien entfernen.
rm config/admin.php database/migrations/2026_01_01_0000*_*admin*.php
rm -r public/vendor/admin
# und die Zeile "admin:publish" aus post-update-cmd in composer.json

# 3. Das Paket entfernen.
composer remove dskripchenko/laravel-admin
```

`migrate:reset --path` rollt nur die Migrationen zurück, die unter den
angegebenen Pfaden gefunden werden, und überspringt den Rest („Migration not
found“), sodass Ihre eigenen Tabellen erhalten bleiben.

## 2. Wer sich anmeldet: `dedicated` oder `shared`

| | `dedicated` (Standard) | `shared` |
|---|---|---|
| Administratoren liegen in | `admin_users`, Modell `Dskripchenko\LaravelAdmin\Models\AdminUser` | Ihrer Tabelle, z. B. `users`, Modell `App\Models\User` |
| Guard | `admin` (Session), vom Paket registriert | Ihrer, z. B. `web` |
| Passwort-Zurücksetzung | Broker `admin_users`, Tabelle `admin_password_resets` | Ihr Broker, z. B. `users` |
| Wer das Admin-Panel öffnen darf | jede aktive Zeile von `admin_users` | nur Benutzer mit mindestens einer Admin-Rolle (oder die `canAccessAdmin()` hereinlässt) |
| Geeignet für | ein Backoffice, dessen Mitarbeiter keine Benutzer der Website sind | ein Konto pro Person: Die Mitarbeiter melden sich bereits auf der Website an |

Wählen Sie im Zweifel `dedicated`: Es rührt Ihre Benutzer niemals an. Alles
Weitere in diesem Abschnitt betrifft `shared`.

### Die Strategie shared Schritt für Schritt

Dieses Rezept wurde vollständig auf einer unveränderten Laravel-13-Anwendung
durchgespielt, deren Tabelle `users` bereits Benutzer enthielt: API-Login,
`system/me`, Theme/Locale, 2FA-Einrichtung und -Login, ein abgewiesener
einfacher Benutzer und ein Login im Browser.

**1. Mit `--shared` installieren:**

```bash
php artisan admin:install --shared
```

Der Befehl liest Ihre Auth-Konfiguration — `auth.defaults.guard`, den Provider
dieses Guards, das Modell des Providers und `auth.defaults.passwords` — und
schreibt sie in `config/admin.php`:

```php
'auth' => [
    'strategy' => env('ADMIN_AUTH_STRATEGY', 'shared'),
    'guard' => env('ADMIN_GUARD', 'web'),
    'provider' => env('ADMIN_PROVIDER', 'users'),
    'model' => \App\Models\User::class,
    'table' => 'admin_users',        // so lassen: siehe unten
    'password_broker' => 'users',
    // ...
],
```

Von Hand sind es dieselben fünf Werte: `ADMIN_AUTH_STRATEGY=shared`,
`ADMIN_GUARD=web`, `ADMIN_PROVIDER=users` in `.env` (oder in der
Konfiguration) sowie `model` / `password_broker` in `config/admin.php` (für
sie gibt es keine env-Schlüssel).

Belassen Sie `auth.table` bei `admin_users`: Die paketeigene Migration
`admin_users` läuft in beiden Strategien und erstellt die dort benannte
Tabelle; würde sie auf `users` zeigen, schlüge diese Migration fehl. In der
Strategie shared bleibt die Tabelle einfach leer.

**2. Die Admin-Spalten zu Ihrer Benutzertabelle hinzufügen.** `--shared`
veröffentlicht `2026_01_01_000100_add_admin_columns_to_users_table.php` (oder
führen Sie `php artisan vendor:publish --tag=admin-shared-migrations` aus),
und `migrate` wendet sie an. Sie ergänzt die Tabelle von `admin.auth.model`
und überspringt jede bereits vorhandene Spalte:

| Spalte | Wofür |
|---|---|
| `locale` `string(8)`, `theme` `string(16)` | die Auswahl des Benutzers im Panel; ohne sie führt das Umschalten von Theme oder Sprache zu einem 500 |
| `is_active` `boolean default true` | Abschalten eines Kontos; `false` verweigert den Admin-Login und beendet laufende Admin-Sessions |
| `last_login_at`, `last_login_ip` | bei jedem Admin-Login geschrieben (übersprungen, wenn nicht vorhanden) |
| `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` | die TOTP-2FA des Admin-Panels |

Beim Zurückrollen werden diese Spalten entfernt. Falls Ihre Tabelle einige
davon schon vorher hatte, löschen Sie diese aus `down()` der Migration.

**3. Zwei Traits zum Modell hinzufügen:**

```php
use Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;

class User extends Authenticatable
{
    use HasAdminAccess, HasAdminTwoFactor, HasFactory, Notifiable;
    // ...
}
```

- `HasAdminAccess` — Admin-Rollen (`roles()` über `admin_role_assignments`,
  `assignRole()`, `revokeRole()`, `hasAccess()`, `getAllPermissions()`).
- `HasAdminTwoFactor` — die verschlüsselten Casts für die 2FA-Spalten und
  `hasTwoFactorEnabled()`. Ohne ihn wird ein Benutzer, der 2FA im Profil
  aktiviert, trotzdem ohne Code hereingelassen.

**4. Jemanden zum Administrator machen.** Einen bestehenden Benutzer, per
E-Mail:

```bash
php artisan admin:user alice@example.com --super
```

oder einen neuen anlegen: `php artisan admin:user "Alice" alice@example.com secret123 --super`.
Im Code: `$user->assignRole('super-admin')` (die Rolle wird vom ersten
`admin:user --super` erstellt) oder eine beliebige eigene Rolle, siehe
[Abschnitt 6](#6-berechtigungen-und-rollen).

**5. Anmelden** unter `/admin/login` mit dem üblichen Passwort des Benutzers.
Über die API:

```bash
# Zuerst ein Session-Cookie und das Cookie XSRF-TOKEN
curl -c jar -b jar -s -o /dev/null http://localhost:8000/admin
XSRF=$(grep XSRF-TOKEN jar | awk '{print $7}' | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

curl -c jar -b jar -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","password":"secret123"}' \
  http://localhost:8000/api/admin/auth/login
# {"success":true,"payload":{"user":{"id":1,...},"permissions":["*"],"redirect_url":"/admin"}}

curl -c jar -b jar -H 'Accept: application/json' http://localhost:8000/api/admin/system/me
# {"success":true,"payload":{"id":1,"name":"Alice",...}}
```

### Wer hineinkommt

In der Strategie shared ist ein Benutzer der Website standardmäßig **kein**
Administrator. Der Login antwortet einem Benutzer ohne Admin-Rolle mit
`403 {"errorKey":"forbidden"}` („You do not have access to the admin panel“),
die Admin-API antwortet ebenso auf die Website-Session eines solchen
Benutzers, und die Shell behandelt ihn als Gast. Die Website-Session des
Benutzers bleibt unberührt.

Um selbst zu entscheiden — Mitarbeiter nach E-Mail-Domain, eine Spalte, ein
Gate —, definieren Sie `canAccessAdmin()` am Modell; seine Antwort ersetzt die
Rollenprüfung (und gilt auch in der Strategie dedicated):

```php
public function canAccessAdmin(string $panelId): bool
{
    return $this->is_staff;
}
```

Ein Modell kann einen Login auch aus eigenen Gründen verweigern (ein
gesperrtes Unternehmen, ein abgelaufener Vertrag) mit
`isDisabledForLogin(): bool`; ein `false` in `is_active` (oder `enabled`)
bewirkt dasselbe.

### Ein Login oder zwei

Mit `ADMIN_GUARD=web` teilen sich Admin-Panel und Website einen Login: Die
Anmeldung im Admin-Panel meldet den Benutzer auf der Website an, und die
Abmeldung aus dem Admin-Panel meldet ihn von der Website ab. Um getrennte
Logins über dieselbe Tabelle zu behalten, deklarieren Sie einen eigenen Guard
in `config/auth.php` und richten das Admin-Panel darauf aus:

```php
// config/auth.php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'admin' => ['driver' => 'session', 'provider' => 'users'],
],
```

```dotenv
ADMIN_AUTH_STRATEGY=shared
ADMIN_GUARD=admin
ADMIN_PROVIDER=users
```

Das Session-Cookie ist weiterhin das der Anwendung; nur der Login-Zustand
wird getrennt gehalten.

### Hinweise zur Strategie shared

- `HasRoles` von `spatie/laravel-permission` definiert ebenfalls `roles()`,
  `assignRole()` und `getAllPermissions()`, daher können die beiden Traits
  nicht in einer Klasse stehen. Legen Sie das Admin-Panel auf eine Unterklasse
  über derselben Tabelle und nennen Sie diese in `admin.auth.model` und im
  Provider (ein separater Guard wie oben):
  `class AdminAccount extends User { use HasAdminAccess, HasAdminTwoFactor; }`.
  Trait-Methoden überschreiben die geerbten.
- Laravel Fortify speichert seine 2FA in denselben Spalten `two_factor_*`,
  verschlüsselt sie aber von Hand. Fügen Sie einem solchen Modell
  `HasAdminTwoFactor` nicht hinzu und aktivieren Sie 2FA nicht aus dem
  Admin-Profil.
- Die Resource „Users“ von `dskripchenko/laravel-admin-starter` bearbeitet
  `admin_users`; schalten Sie sie ab (`'resources' => ['users' => false]` in
  `config/admin-starter.php`) und verwalten Sie Ihre Benutzer mit einer
  eigenen Resource.
- Der Test-Helfer `ActsAsAdmin` folgt der Strategie: In einer
  shared-Anwendung erstellt er Ihren eigenen Benutzer (`admin.auth.model`),
  weist ihm eine Rolle zu und meldet ihn über Ihren Guard an.

## 3. Pfad, Domain, API, Sessions, Proxys

| Einstellung | Standard | Wirkung |
|---|---|---|
| `ADMIN_PATH` | `admin` | die SPA liegt unter `/{path}/*`; `''` bindet sie an der Wurzel ein |
| `ADMIN_DOMAIN` | keine | bindet die SPA-Routen an einen Host, z. B. `admin.example.com` |
| `laravel-api.prefix` | `api` | die API liegt unter `/{prefix}/admin/{controller}/{action}` |
| `ADMIN_API_PATH` | abgeleitet | die API-URL, die der SPA übergeben wird; lassen Sie sie ungesetzt |

```dotenv
ADMIN_PATH=backoffice     # → /backoffice/login
```

**Der API-Pfad.** Die API des Admin-Panels ist eine Version von
`dskripchenko/laravel-api` und wird daher unter dem Präfix von laravel-api
ausgeliefert: standardmäßig `/api/admin/*`, `/backend/admin/*` mit
`'prefix' => 'backend'` in `config/laravel-api.php`. Die SPA bezieht ihre
API-URL aus demselben Präfix, sodass eine Verschiebung des Präfixes beides
verschiebt. Setzen Sie `ADMIN_API_PATH` nur, wenn ein Proxy den Pfad
umschreibt, den der Browser sieht; die Routen verschiebt es nicht.

**Eine separate Domain.** `ADMIN_DOMAIN` beschränkt die Shell-Routen; die
API-Routen sind nicht an eine Domain gebunden, und die SPA ruft sie auf dem
Host auf, von dem sie geladen wurde. Mit `ADMIN_DOMAIN=admin.example.com` und
`ADMIN_PATH=` ist das Admin-Panel die Wurzel dieses Hosts und verdeckt die
Hauptseite nicht; das API-Präfix bleibt außerhalb des Catch-all der Shell.

**Sessions und Cookies.** Das Admin-Panel nutzt die Session der Anwendung
(`config/session.php`): Die Middleware-Gruppe `web` ist der erste Eintrag
sowohl von `admin.middleware.shell` als auch von `admin.middleware.api`. Was
Sie für die Website einstellen — Treiber, Lebensdauer, `SESSION_DOMAIN`,
`SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` —, gilt auch für das
Admin-Panel. Wenn das Admin-Panel auf einer eigenen Subdomain liegt und den
Login mit der Website teilen soll, setzen Sie `SESSION_DOMAIN=.example.com`.

**CSRF.** Jeder API-Aufruf des Admin-Panels läuft durch `web`, einschließlich
der CSRF-Prüfung. Die SPA sendet `X-XSRF-TOKEN` aus dem Cookie `XSRF-TOKEN`
automatisch. Ein Skript, das die API mit einer Session aufruft, muss dasselbe
tun (siehe das `curl`-Beispiel oben); ein Request ohne ihn erhält `419`.
Maschinelle Clients verwenden stattdessen die persönlichen API-Tokens aus dem
Profil (Sanctum, sofern installiert).

**Hinter einem Proxy oder Load Balancer.** Die Shell übergibt der SPA
absolute URLs, die mit `url()` gebaut werden. Hinter einem TLS-terminierenden
Proxy, dem nicht vertraut wird, entstehen sie als `http://…`, und der Browser
blockiert sie als Mixed Content. Vertrauen Sie dem Proxy in
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');   // oder die Adressen des Proxys
})
```

und setzen Sie `APP_URL` auf die öffentliche URL.

**Rate Limits.** `ADMIN_LOGIN_THROTTLE` (`5,1` — fünf Versuche pro Minute)
für den Login und die 2FA-Schritte, drei E-Mails zur Passwort-Zurücksetzung
pro fünf Minuten und `ADMIN_API_THROTTLE` (`240,1`) für den Rest der API.

## 4. Mehrere Panels

Ein Panel — `admin` — deckt die meisten Anwendungen ab. Fügen Sie ein Panel
hinzu, wenn ein zweites Publikum eine eigene Oberfläche braucht: ein
Kundenbereich neben dem Backoffice, mit eigenen Benutzern, eigener
Login-Seite, eigenem Menü und eigenen Resources.

Jedes Panel hat einen eigenen Mount-Pfad, Guard und ein eigenes
Benutzermodell, eine eigene API-Version (`/api/{id}/*`), Middleware und
Plugins. Das Standard-Panel wird aus den Schlüsseln der obersten Ebene von
`config/admin.php` gebildet; die übrigen werden in `admin.panels` aufgeführt:

```php
// config/admin.php
'panels' => [
    'client' => [
        'path' => 'cabinet',                         // die SPA unter /cabinet
        'auth' => [
            'strategy' => 'dedicated',               // Guard/Provider/Broker werden für Sie registriert
            'guard' => 'client',
            'provider' => 'client_users',
            'model' => App\Models\ClientUser::class, // HasAdminAccess, eine Spalte `enabled` oder `is_active`
            'table' => 'client_users',
            'password_broker' => 'client_users',
        ],
        'api' => App\Admin\ClientApi::class,         // die API unter /api/client/*
        'plugins' => [App\Admin\ClientPanelPlugin::class],
    ],
],
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Panel\PanelApi;

final class ClientApi extends PanelApi {}   // eine Unterklasse pro Panel
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

final class ClientPanelPlugin implements AdminPlugin
{
    public function name(): string { return 'client-panel'; }
    public function version(): string { return '1.0.0'; }
    public function register(): void {}

    public function boot(Admin $admin): void
    {
        // Hier registriert, gehören die Resources nur zum Panel `client`.
        $admin->resources([ProjectResource::class]);
        $admin->menu()->add(MenuNode::resource('projects'));
    }
}
```

Die Tabelle `client_users` und das Modell legen Sie selbst an. Ein an der
Wurzel eingebundenes Panel (`'path' => ''`) muss die Präfixe aufführen, die
es nicht verschlucken soll: `'exclude_prefixes' => ['api', 'admin']`. Die
Einträge in `middleware.api` eines Panels werden zum gemeinsamen Stack
`admin.middleware.api` hinzugefügt.

## 5. Ihr eigenes `dskripchenko/laravel-api`-Modul

laravel-api löst alle API-Versionen über ein einziges Binding `api_module`
auf. Das Admin-Panel bindet es an
`Dskripchenko\LaravelAdmin\Http\AdminApiModule`, dessen Versionen `admin` und
die Panels sind. Bindet Ihre Anwendung ein eigenes Modul — der übliche
`ApiServiceProvider extends
Dskripchenko\LaravelApi\Providers\ApiServiceProvider` mit `getApiModule()` —,
wird Ihres später registriert und gewinnt, und ein Modul, das `BaseModule`
von laravel-api erweitert, verwirft die Admin-API: `/api/admin/*` wird zu
einem 404.

Erweitern Sie stattdessen `AdminApiModule` und fügen Sie Ihre Versionen zur
Liste der Elternklasse hinzu:

```php
namespace App\Api;

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;

class ApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return array_merge(parent::getApiVersionList(), [
            'v1' => V1Api::class,          // Ihre BaseApi
        ]);
    }
}
```

```php
namespace App\Providers;

use App\Api\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseApiServiceProvider;

class ApiServiceProvider extends BaseApiServiceProvider
{
    protected function getApiModule()
    {
        return new ApiModule;
    }
}
```

Dann werden `/api/v1/*` und `/api/admin/*` nebeneinander ausgeliefert.

- Überschreiben Sie `getApiMiddleware()` nicht. Seit 1.32.0 ist die
  Middleware-Gruppe des Moduls versionsunabhängig, und der Stack wird pro
  Request gewählt: Das Admin-Panel und die Panels erhalten
  `admin.middleware.api` (Session, CSRF, `AdminAuth`), Ihre Versionen nur die
  Middleware, die ihre eigene `BaseApi::getMethods()` deklariert. Eine
  zustandslose `v1` mit Bearer-Tokens erhält daher vom Admin-Panel weder
  Session noch CSRF; braucht eine Version die Session oder `AdminLocale`,
  deklariert sie diese selbst.
- `getApiPrefix()` und `getApiUriPattern()` lesen `laravel-api.prefix` und
  `laravel-api.uri_pattern`, die von allen Versionen geteilt werden. Eine
  Änderung des Präfixes verschiebt auch die Admin-API, und die SPA folgt
  (siehe [Abschnitt 3](#3-pfad-domain-api-sessions-proxys)).
- Verwenden Sie keine Versions-ID, die einer Panel-ID entspricht (`admin`
  oder ein Schlüssel von `admin.panels`).

## 6. Berechtigungen und Rollen

Berechtigungen sind Strings, `admin.{domain}.{action}`, mit Wildcards
(`admin.articles.*`, `*`). Rollen (`admin_roles`) enthalten Listen davon und
werden Benutzern über `admin_role_assignments` zugewiesen; ein Benutzer kann
mehrere haben. Jede Resource erhält `admin.{slug}.view/create/update/delete`
(plus restore, replicate usw., wenn aktiviert); Screens und Settings werden
auf dieselbe Weise geschützt. Siehe
[Berechtigungen](concepts/permissions.md).

```bash
php artisan admin:user --super                 # interaktiv; Super Admin = ['*']
php artisan admin:user alice@example.com --super   # einem bestehenden Benutzer vergeben
```

```php
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

$editor = Role::create([
    'name' => 'Redakteur',
    'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($editor);        // oder ->assignRole('editor')
$user->hasAccess('admin.articles.update');   // true
$user->revokeRole('editor');
```

Eine Oberfläche für Rollen bringt das Starter-Pack mit:

```bash
composer require dskripchenko/laravel-admin-starter
```

Es registriert sich selbst und fügt die Resources „Users“ (`admin_users`),
„Roles“ (Berechtigungen auswählbar aus denen aller registrierten Resources
und Plugins) und ein schreibgeschütztes „Audit log“ hinzu. In der Strategie
shared schalten Sie die Resource „Users“ ab, siehe oben.

### Zwei-Faktor-Authentifizierung

Jeder Benutzer kann im Profil die TOTP-Zwei-Faktor-Authentifizierung
einschalten. Zwei Einstellungen in `config/admin.php` ändern das:

```php
'auth' => [
    'two_factor' => [
        // false: Das Profil bietet keine 2FA-Einrichtung an, und das Aktivieren wird abgelehnt
        // (403 two_factor_disabled). Benutzer, die sich früher registriert haben, durchlaufen
        // die Abfrage beim Login weiterhin und können sie weiterhin abschalten.
        'enabled' => true,
        // Rollen-Slugs, deren Inhaber zuerst 2FA aktivieren müssen; '*' bedeutet alle.
        'enforce_for' => ['super-admin', 'security'],
    ],
],
```

Ein Benutzer, der unter `enforce_for` fällt und 2FA noch nicht eingerichtet
hat, erreicht nur das Profil, die Session und die Shell: Jeder andere
API-Request antwortet mit 403 und `errorKey: two_factor_setup_required`, und
die SPA hält ihn im Abschnitt „Sicherheit“ des Profils, bis 2FA eingeschaltet
ist.

## 7. Das Frontend

**Vorgebaut (Standard).** Das Paket liefert die SPA gebaut aus;
`admin:install` kopiert sie nach `public/vendor/admin`, und die Shell lädt
sie. Die Kopie muss nach jeder Aktualisierung des Pakets erneuert werden —
genau das erledigt der Composer-Hook:

```json
"post-update-cmd": [
    "@php artisan admin:publish --ansi"
]
```

Falls Sie den Hook übersprungen haben, fügen Sie ihn hinzu oder führen Sie
`php artisan admin:publish` in Ihrem Deploy nach `composer install` aus.
`public/vendor/admin` kann wie jedes andere öffentliche Asset committet oder
in der CI gebaut werden.

**Ihr eigener Build** — für eigene Felder, Widgets, Layouts oder Seiten, die
in Vue geschrieben sind:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

Die Shell lädt dann Ihren Vite-Build (`assets.vite_manifest` /
`assets.vite_entry` in `config/admin.php`) statt der vorgebauten Kopie.
Halten Sie das npm-Paket auf derselben Version wie das Composer-Paket. Siehe
[Frontend-Erweiterung](frontend-extension.md).

## 8. Upgrade und Fehlerbehebung

### Upgrade

```bash
composer update dskripchenko/laravel-admin
php artisan migrate                  # neue Migrationen werden aus vendor/ geladen
php artisan admin:publish            # erledigt der Composer-Hook für Sie
php artisan config:clear             # falls Sie die Konfiguration cachen
```

Lesen Sie zuerst das [CHANGELOG](../../CHANGELOG.md). Die Konfiguration des
Pakets wird eine Ebene tief unter Ihre gemischt: Ein neuer Schlüssel der
obersten Ebene von `config/admin.php` erhält den Standardwert des Pakets, ein
neuer Schlüssel innerhalb eines Blocks, den Sie veröffentlicht haben (etwa
innerhalb von `auth`), existiert jedoch erst, wenn Sie ihn übernehmen. Wenn
ein Release eine Konfigurationsänderung erwähnt, vergleichen Sie Ihre Datei
mit `vendor/dskripchenko/laravel-admin/config/admin.php`. Bei einem eigenen
Build aktualisieren Sie die npm-Pakete auf dieselbe Version und bauen neu.

### Fehlerbehebung

**Eine leere Seite oder „The admin frontend was not found“.** Die Shell hat
kein Frontend gefunden: Führen Sie `php artisan admin:publish` (vorgebaut)
oder `npm run build` aus (eigener Build; prüfen Sie, dass
`assets.vite_manifest` auf ein existierendes Manifest zeigt). Ein 404 auf
`/vendor/admin/assets/*` bedeutet, dass `public/vendor/admin` den Server nicht
erreicht hat — ein Deploy, der `public/vendor` nicht ausliefert, oder ein
Webserver, dessen Document Root nicht `public/` ist.

**Eine Leiste „The admin files are out of date: run php artisan admin:publish“.**
Die veröffentlichte Kopie weicht von der im installierten Paket ab — das
Paket wurde aktualisiert, ohne neu zu veröffentlichen. Führen Sie
`admin:publish` aus und fügen Sie den Composer-Hook hinzu, damit das nicht
erneut passiert.

**401 direkt nach einem erfolgreichen Login.** Das Session-Cookie kommt nicht
zurück:
- `SESSION_SECURE_COOKIE=true`, während das Admin-Panel über einfaches HTTP
  ausgeliefert wird;
- `SESSION_DOMAIN` passt nicht zu dem Host, auf dem das Admin-Panel geöffnet
  wird (oder das Admin-Panel liegt auf einer Subdomain, und die Domain wird
  nicht geteilt);
- der Session-Treiber `array` oder ein Cache-/Session-Store, der nicht
  zwischen den Servern geteilt wird;
- ein Proxy, dem nicht vertraut wird, sodass der Request wie HTTP aussieht
  (siehe [Abschnitt 3](#3-pfad-domain-api-sessions-proxys)).
`401 {"errorKey":"session_expired"}` bedeutet, dass das Passwort des Benutzers
seit dem Login geändert wurde: Melden Sie sich erneut an.

**403 „You do not have access to the admin panel“.** Die Strategie shared und
ein Benutzer ohne Admin-Rolle — vergeben Sie eine (`admin:user email --super`).
`403 account_inactive` ist ein abgeschaltetes Konto (`is_active` oder
`enabled` ist false, oder `isDisabledForLogin()` hat es so entschieden).

**419 bei API-Aufrufen aus einem Skript.** Der Request hat kein CSRF-Token:
Senden Sie `X-XSRF-TOKEN` aus dem Cookie oder verwenden Sie ein API-Token.

**Die falsche Sprache.** Die Locale des Admin-Panels wird in dieser
Reihenfolge gewählt aus: `?locale=`, dem Header `X-Admin-Locale` der SPA, der
Spalte `locale` des Benutzers, dem Cookie `admin_locale`, dem
`Accept-Language` des Browsers und schließlich `ADMIN_LOCALE` /
`admin.ui.default_locale` oder `config('app.locale')`. Berücksichtigt werden
nur die Sprachen in `admin.ui.available_locales` (standardmäßig
`['ru', 'en']`). Übersetzungen Ihrer eigenen Strings gehören in
`lang/{locale}.json`.

**`/api/admin/*` ist ein 404.** Ihre Anwendung bindet ein eigenes
laravel-api-Modul, das `AdminApiModule` nicht erweitert — siehe
[Abschnitt 5](#5-ihr-eigenes-dskripchenkolaravel-api-modul). Oder die Routen
sind noch von vor der Installation gecacht: `php artisan route:clear`.

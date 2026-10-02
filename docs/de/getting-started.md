---
title: Erste Schritte
audience: developer
status: stable
locale: de
translated_from: en/getting-started.md
translated_at: 2026-10-02
---

# Erste Schritte

Diese Anleitung führt Sie in etwa zehn Minuten von einer frischen
Laravel-Anwendung zu einem funktionierenden Admin-Panel mit einer eigenen
Resource.

## Voraussetzungen

- PHP 8.2+
- Laravel 11, 12 oder 13
- Node — nur, wenn Sie das Frontend selbst bauen (`--custom-build`)
- Ein Eloquent-Modell, das Sie verwalten möchten (wir verwenden `Article`)

## Installation

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` veröffentlicht `config/admin.php` und die Migrationen,
veröffentlicht das vorgebaute Frontend nach `public/vendor/admin`, führt
`migrate` aus und legt den ersten Administrator an. Es erstellt `admin_users`,
`admin_roles`, `admin_settings`, `admin_audit_logs`, `admin_dashboard_layouts`
und einige weitere Tabellen.

Sie fügen das Admin-Panel zu einer Anwendung hinzu, die bereits Benutzer hat?
Mit `php artisan admin:install --shared` melden sich diese mit ihren üblichen
Konten an statt über eine separate Tabelle `admin_users` — siehe
[Das Admin-Panel zu einer bestehenden Anwendung hinzufügen](integration.md).

Weder Node noch ein Build-Schritt sind nötig: Das Paket liefert die Admin-SPA
bereits gebaut aus. `admin:install` bietet außerdem an,
`php artisan admin:publish` zu composers `post-update-cmd` hinzuzufügen, damit
das Frontend bei jeder Aktualisierung des Pakets neu veröffentlicht wird (das
Admin-Panel zeigt eine Warnung, wenn die veröffentlichte Kopie veraltet ist).

Optionen: `--no-migrate`, `--no-user`, `--no-composer-hook`, `--force`
(veröffentlichte Dateien überschreiben), `--custom-build` (siehe unten).

### Eigene Felder, Widgets oder Seiten: Ihr eigener Build

Um eigene Vue-Komponenten zu registrieren, bauen Sie das Admin-Panel mit dem
Vite Ihrer Anwendung, statt das vorgebaute Bundle zu verwenden:

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

`--custom-build` erstellt `resources/js/admin.js`, fügt es zu den Inputs von
`laravel-vite-plugin` in `vite.config.js` hinzu und richtet
`config('admin.assets')` auf das Vite-Manifest aus. Registrieren Sie Ihre
Komponenten in diesem Einstiegspunkt vor `createAdminApp()` — siehe
[Frontend-Erweiterung](frontend-extension.md).

## Den ersten Admin-Benutzer anlegen

Falls Sie diesen Schritt bei `admin:install` übersprungen haben oder weitere
Administratoren benötigen:

```bash
php artisan admin:user --super
```

Der Befehl fragt nach Name, E-Mail und Passwort (oder nimmt sie als Argumente
entgegen: `admin:user "Admin" admin@example.com secret123 --super`). `--super`
vergibt die Rolle mit allen Berechtigungen.

Öffnen Sie `/admin/login` mit diesen Zugangsdaten. Der Pfad stammt aus
`ADMIN_PATH` (Standard `admin`).

## Ihre erste Resource

Erzeugen Sie ein Grundgerüst mit dem interaktiven Assistenten — er fragt nach
den Bezeichnungen, dem Modell (oder einer Tabelle), den Formular- und
Tabellenspalten, der Berechtigung und dem Icon:

```bash
php artisan admin:make-resource
```

Oder schreiben Sie sie von Hand:

```php
namespace App\Admin\Resources;

use App\Models\Article;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;
    public static string $icon  = 'file-text';

    public static function label(): string { return 'Artikel'; }

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Input::make('slug')->required(),
            Textarea::make('excerpt')->rows(3),
            Select::make('status')->options([
                'draft' => 'Entwurf',
                'review' => 'In Prüfung',
                'published' => 'Veröffentlicht',
                'archived' => 'Archiviert',
            ])->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('status')->asBadge(),
            TableColumn::make('created_at')->asDateTime()->sort(),
        ];
    }
}
```

Registrieren Sie sie in Ihrem `AppServiceProvider::boot()`:

```php
use Dskripchenko\LaravelAdmin\Facades\Admin;
use App\Admin\Resources\ArticleResource;

public function boot(): void
{
    Admin::resources([ArticleResource::class]);
}
```

Das ist alles. Die Screens für Liste/Anlegen/Bearbeiten/Ansicht werden
automatisch erzeugt:

| URL | Was |
|---|---|
| `/admin/r/articles` | Liste + Filter + Paginierung |
| `/admin/r/articles/create` | Formular zum Anlegen |
| `/admin/r/articles/{id}/edit` | Formular zum Bearbeiten |
| `/admin/r/articles/{id}` | Schreibgeschützte Infolist |

## Nächste Schritte

- [Das Admin-Panel zu einer bestehenden Anwendung hinzufügen](integration.md) —
  Ihre bestehenden Benutzer, Pfad und Domain, Proxys, mehrere Panels, Ihr
  eigenes laravel-api-Modul, Upgrade und Fehlerbehebung.
- [Hierarchisches Menü](concepts/menu.md) — die automatische Befüllung durch
  einen expliziten Navigationsbaum ersetzen.
- [Custom Screens](concepts/screens.md) — Nicht-CRUD-Seiten (Formulare,
  Berichte).
- [Berechtigungen](concepts/permissions.md) — Zugriff pro Action steuern.
- [Referenz der Felder](fields-reference.md) — vollständiger Katalog der Felder.
- [Referenz der Layouts](layouts-reference.md) — Tabs/Wizard/Modal/Drawer.
- [Demo-Modus](demo-mode.md) — ein öffentlicher Demonstrationsstand:
  Demo-Konten per Klick und ein Schreibschutz.

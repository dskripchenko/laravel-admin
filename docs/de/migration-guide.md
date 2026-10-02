---
title: Migrationsleitfaden
audience: developer
status: stable
locale: de
translated_from: en/migration-guide.md
translated_at: 2026-10-02
---

# Migrationsleitfaden

Upgrades zwischen Major- und Minor-Versionen. Wir folgen [SemVer](https://semver.org).

## 1.4.x → 1.36.x

Eine 2.0 gibt es noch nicht, und keines dieser Releases erfordert ein Umschreiben, aber
einige ändern Verhalten, auf das sich ein Host verlassen könnte. Neueste zuerst:

- **1.36.0** — erfordert `dskripchenko/laravel-api` `^5.11`.
- **1.34.0** — erfordert `@dskripchenko/ui` `^1.4.0` (nur relevant für einen
  eigenen Build). Menüeinträge mit gleichem `order` behalten die Reihenfolge, in der sie
  hinzugefügt wurden, statt alphabetisch sortiert zu werden. Konsolenbefehle
  (`admin:user`, `admin:make-*`) sprechen Englisch.
- **1.33.0** — `admin:install` veröffentlicht das vorgebaute Frontend nach
  `public/vendor/admin`; Node wird nicht mehr benötigt, es sei denn, Sie registrieren
  eigene Vue-Komponenten. Die Standard-Locale des Admins folgt `config('app.locale')`,
  sofern `ADMIN_LOCALE` / `admin.ui.default_locale` nicht gesetzt ist (früher war sie
  Russisch) — setzen Sie sie explizit, wenn Sie sich auf den alten Standard verlassen haben.
- **1.32.0** — Host-API-Versionen, die neben dem Admin eingebunden werden
  (`parent::getApiVersionList()`), erben den Middleware-Stack des Admins nicht mehr.
  Wenn eine Ihrer Versionen auf `AdminLocale` oder die daraus stammende Session
  angewiesen war, deklarieren Sie diese in `getMethods()` dieser Version.
- **1.27.0** — die Profil-Tabs „API-Tokens“ und „Sessions“ werden nur angezeigt,
  wenn der Host den entsprechenden Slot befüllt.
- **1.17.0** — gespeicherte Listenansichten sind Opt-in: Geben Sie `true` aus
  `Resource::savedViews()` bei den Resources zurück, die sie nutzen; die
  `{slug}_views/*`-Routen werden nur für diese registriert.
- **1.16.0** — die Action `{slug}/exportCsv` entfällt; rufen Sie
  `{slug}/export?format=csv` auf.
- **1.15.0** — Resource-Actions werden nach Fähigkeit registriert: `tree` und
  `treeScreen` nur für hierarchische Resources, `restore` und `forceDelete`
  nur mit `SoftDeletes`, `replicate` und `reorder` nur bei
  `replicable()` / `reorderable()`. Eine nicht unterstützte Action antwortet mit 404
  statt 409/422.
- **1.7.0** — die Support-Matrix ist PHP 8.2–8.5 und Laravel 11/12/13;
  `dskripchenko/laravel-api` `^5.0`.
- **1.5.6** — auf einer Ansichtsseite ohne eigenes `infolist()` werden `Switcher`-Felder
  als Ja/Nein-`IconEntry` dargestellt statt als rohes `true`/`false`.

Führen Sie nach dem Upgrade `php artisan migrate` aus: Neue Tabellen und Spalten kommen als
Migrationen. Für alles Weitere siehe [`CHANGELOG.md`](../../CHANGELOG.md).

## 1.3.x → 1.4.0

**Keine Breaking Changes.** Neue Funktionen sind Opt-in. Nennenswerte Ergänzungen:

### Neu: hierarchisches Menü (M1+M2)

```php
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
```

Wenn Sie `Admin::menu()` nicht aufrufen, bleibt das automatische Befüllen aus 1.2.x
erhalten.

### Neu: Custom Screens (P21+P22)

```php
class ContactScreen extends Screen { /* ... */ }

Admin::screen([ContactScreen::class]);
```

URL: `/admin/screens/contact`. Siehe
[concepts/screens.md](concepts/screens.md).

### Neu: Widget-Polling und rowSpan

```php
StatsOverviewWidget::make()
    ->title('Live')
    ->refresh(30)        // alle 30 s abfragen
    ->rowSpan(2);        // 2 Rasterzeilen hoch (1.4.0)
```

`refresh()` gab es bereits in 1.2.x, aber das Frontend hat es ignoriert. Jetzt
löst es ein `setInterval` auf `/dashboard/widgets` aus.

### Frontend: Prop-Filter von WidgetRenderer

Wenn Sie eine eigene Widget-Vue-Komponente geschrieben haben, die auf
`props.size` (in Pixeln) zugegriffen hat, müssen Sie eine eigene Prop für die Pixelgröße hinzufügen —
die bisherige Prop `size` war eine Spaltenspanne im Raster (1..12); die Mehrdeutigkeit
zwischen Spanne und Pixeln wird nun dadurch aufgelöst, dass Dashboard-Metafelder
entfernt werden. Siehe
[frontend-extension.md → Eigenes Widget](frontend-extension.md#eigenes-widget).

## 1.2.x → 1.3.0

Veröffentlicht als erste Fassung der Custom-Screens-API. Alle Änderungen von 1.4.0
haben hier begonnen; wir empfehlen, direkt auf 1.4.0 zu aktualisieren
(keine Breaking Changes).

## 1.1.x → 1.2.0

### Neu: Dashboard-Widgets

`Widget::class` und `DashboardScreen::class` wurden eingeführt. Die Sister-Packs
wurden nicht hochgezogen — sie funktionieren weiterhin mit `^1.2.0`.

### Neu: 2FA TOTP

Die Spalten `two_factor_secret`, `two_factor_recovery_codes` und
`two_factor_confirmed_at` werden per Migration zu `admin_users` hinzugefügt.
Führen Sie nach dem Upgrade `php artisan migrate` aus.

### Glocken-Benachrichtigungen

`SystemController::me` liefert jetzt `unread_notifications_count`. Wenn
Sie die `me`-Payload überschrieben haben, ergänzen Sie das neue Feld.

## 1.0.x → 1.1.0

(Vor-stabil, kein veröffentlichtes 1.0.x-Release. 1.1.0 war die erste stabile
Veröffentlichung.)

## Upgrade-Checkliste (allgemein)

Für jeden Minor-/Patch-Sprung:

```bash
composer update dskripchenko/laravel-admin
php artisan migrate
php artisan admin:publish        # vorgebautes Frontend erneut veröffentlichen (der Composer-Hook erledigt das ebenfalls)
php artisan optimize:clear
```

Mit eigenem Build (`admin:install --custom-build`) aktualisieren Sie statt `admin:publish`
die npm-Pakete und bauen neu:

```bash
npm update @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

Die Paketkonfiguration wird eine Ebene tief mit Ihrer zusammengeführt: Ein neuer Schlüssel innerhalb
eines Blocks, den Sie veröffentlicht haben (etwa innerhalb von `auth`), erscheint erst, wenn Sie ihn
übernehmen — vergleichen Sie mit `vendor/dskripchenko/laravel-admin/config/admin.php`,
wenn ein Release eine Konfigurationsänderung erwähnt.

Bei Major-Sprüngen zusätzlich:

1. Lesen Sie den entsprechenden Abschnitt dieses Leitfadens von oben bis unten.
2. Überfliegen Sie `CHANGELOG.md` nach Breaking Changes.
3. Führen Sie vor dem Deployment Ihre vollständige Testsuite aus.

## Siehe auch

- [`CHANGELOG.md`](../../CHANGELOG.md)
- [Architektur](architecture.md)
- [Den Admin in eine bestehende Anwendung integrieren → Upgrade](integration.md#8-upgrade-und-fehlerbehebung)

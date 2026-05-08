---
title: Glossar
audience: developer
status: stable
locale: de
translated_from: en/glossary.md
translated_at: 2026-05-08
---

# Glossar

Gemeinsame Terminologie für das gesamte
`dskripchenko/laravel-admin`-Ökosystem (`laravel-admin`, Sister-Packs,
`@dskripchenko/ui`, `@dskripchenko/wysiwyg`).

## Resource

Eine Klasse, die `Dskripchenko\LaravelAdmin\Resource\Resource`
erweitert. Beschreibt, wie ein einzelnes Eloquent-Modell in der
Admin-Oberfläche dargestellt wird: Felder (Formular), Spalten
(Tabelle), Filter, Aktionen, Berechtigungen. Wird über ein
`Repository` betrieben und durch `GeneratedListScreen` /
`GeneratedEditScreen` / `GeneratedViewScreen` gerendert.

## Screen

Eine abstrakte Seite (Orchid-Stil): Klasse, die
`Dskripchenko\LaravelAdmin\Screen\Screen` erweitert, mit `query()`
(State), `layout()` (Renderable-Baum), `commandBar()` (Aktionen). Ein
*Custom Screen* ist alles, was nicht aus einer `Resource`
auto-generiert wird — registriert über `Admin::screen([...])`.

## Field

Ein Formular-Eingabe-Deskriptor: `Input`, `Textarea`, `Number`,
`Select`, `Wysiwyg`, `Repeater` usw. Jedes Feld definiert
Validierungsregeln, Default-Wert, Sichtbarkeit pro Modus
(create/update/view) und optionale typ-spezifische Konfiguration. Das
Frontend rendert Felder über `FieldRenderer` (JSON-driven).

## Layout

Renderable-Container, der Felder oder andere Layouts enthält: `Rows`,
`Columns`, `Tabs`, `Wizard` + `Step`, `Block`, `Modal`, `Drawer`,
`Wrapper`, `Infolist`, `View` (custom Vue-Komponente). Beliebig tief
verschachtelbar.

## Action

Button/Link/Dropdown an einem Screen, einer Zeile oder einer
Bulk-Auswahl: `Button`, `Link`, `BulkAction`, `ModalAction`,
`DropDown`, `AsyncAction`. Aktionen lösen eine Controller-Methode
aus (z.B. `Button::method('save')`).

## Filter

Tabellen-Filter-Deskriptor für Listen-Screens: `BaseInputFilter`,
`BaseDateFilter`, `BaseSwitcherFilter`, `BaseSelectFromModelFilter`,
`BaseSelectFromQueryFilter`, `BaseSelectFromOptionsFilter`,
`TrashedFilter`. Aus dem HTTP-Query durch `HttpFilterParser` geparst.

## Permission

Namespaced String wie `admin.users.view`, `admin.articles.update`. Wird
bei jeder Aktion durch das `AdminAccess`-Middleware geprüft; Benutzer
halten Permissions über `Role`s. Wildcards `*` und `admin.users.*`
werden unterstützt.

## Manifest

Einzelnes JSON-Dokument `/api/admin/system/manifest`, das beim
Bootstrap an die SPA zurückgegeben wird: `{resources, screens, settings,
dashboards, plugins, permissions, version}`. Das Frontend baut Vue
Router Routes und Sidebar daraus; ETag-basiertes Caching.

## Plugin

Klasse, die `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin`
implementiert — host-seitig oder ein Sister-Pack, das Resources/
Screens/Settings/Permissions/Menu-Nodes über `register()` und
`boot(Admin $admin)` beisteuert.

## Tenant

Optionales Multi-Tenancy-Primitiv (`TenantResolver`, `TenantContext`,
`TenantScoped`-Trait). Auflösungsstrategie ist host-seitig; das Admin
liefert nur den Vertrag.

## Widget / Dashboard

`Widget` — eine einzelne Dashboard-Kachel (`Stats`, `Chart`,
`RecentList`, `Heatmap`, `Gauge`, `Markdown`, `Iframe`).
`DashboardScreen` aggregiert Widgets mit optionalen Layout-Overrides
pro Benutzer. Dashboards leben unter `/dashboard/{slug}`.

## Settings

Single-Row-Konfigurationsscreen (Singleton-Style). `SettingsResource`
definiert Felder und persistiert Werte über `SettingsStorage`
(Default: `KeyValueSettingsStorage` über die Tabelle
`admin_settings`).

## Audit

Append-only Log von Admin-Aktionen (`AuditLog`-Modell + `Loggable`-
Trait). Gerendert über `AuditTrail`-Layout und `AuditController`.

## Translatable

Field-level i18n für Eloquent-Modelle, bereitgestellt von
`dskripchenko/laravel-translatable`. Das Admin verbindet
translatable Modelle mit `TranslatableInput` / `TranslatableField`
(Tabs pro Locale).

## Bootstrap

Initiales Payload, das die SPA vor dem Mounten benötigt: CSRF-Token,
Base-URL, Locale, Theme, Brand, aktueller Benutzer, Manifest-Version.
Zwei Strategien: `inline` (Blade-injiziertes `<script>`, default) oder
`xhr` (`/api/admin/system/bootstrap`).

## Siehe auch

- [English](../en/glossary.md)
- [Русский](../ru/glossary.md)
- [中文](../zh/glossary.md)

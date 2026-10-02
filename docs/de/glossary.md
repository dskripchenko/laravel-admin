---
title: Glossar
audience: developer
status: stable
locale: de
translated_from: en/glossary.md
translated_at: 2026-10-02
---

# Glossar

Gemeinsame Terminologie für das gesamte `dskripchenko/laravel-admin`-Ökosystem
(`laravel-admin`, Sister-Packs, `@dskripchenko/ui`, `@dskripchenko/wysiwyg`).

## Resource

Eine Klasse, die `Dskripchenko\LaravelAdmin\Resource\Resource` erweitert. Beschreibt,
wie ein einzelnes Eloquent-Modell im Admin bereitgestellt wird: Felder (Formular),
Spalten (Tabelle), Filter, Actions, Berechtigungen. Dargestellt über
`GeneratedListScreen` (bzw. `GeneratedTreeScreen` für eine hierarchische
Resource) / `GeneratedCreateScreen` / `GeneratedEditScreen` /
`GeneratedViewScreen`.

## Screen

Eine abstrakte Seite (im Stil von Orchid): eine Klasse, die
`Dskripchenko\LaravelAdmin\Screen\Screen` erweitert, mit `query()` (State),
`layout()` (darstellbarer Baum), `commandBar()` (Actions). Ein *Custom Screen*
ist alles, was nicht automatisch aus einer `Resource` generiert wird — registriert über
`Admin::screen([...])`.

## Field (Feld)

Ein Deskriptor für ein Formular-Eingabeelement: `Input`, `Textarea`, `Number`, `Select`,
`Wysiwyg`, `Repeater` usw. Jedes Feld deklariert Validierungsregeln,
einen Standardwert, die Sichtbarkeit pro Modus (create/update/view) und eine optionale
typspezifische Konfiguration. Das Frontend rendert ein Feld über `FieldRenderer`
(JSON-gesteuert).

## Layout

Ein darstellbarer Container, der Felder oder andere Layouts enthält: `Rows`,
`Columns`, `Tabs`, `Wizard` + `Step`, `Block`, `Modal`, `Drawer`,
`Wrapper`, `Infolist`, `View` (eigene Vue-Komponente). Beliebig tief
kombinierbar.

## Action

Ein Button/Link/Dropdown, der einem Screen, einer Zeile oder einer Mehrfachauswahl zugeordnet ist:
`Button`, `Link`, `BulkAction`, `ModalAction`, `DropDown`, `AsyncAction`.
Actions lösen eine Methode am Screen oder an der Resource aus (z. B.
`Button::make('Speichern')->method('save')`).

## Filter

Ein Deskriptor für Tabellenfilter von List-Screens, erzeugt mit
`::for('column')`: `InputFilter`, `OptionsFilter`, `DateRangeFilter`,
`SwitcherFilter`, `SelectFromModelFilter`, `QueryFilter`, `TrashedFilter`.
Wird von `HttpFilterParser` aus der HTTP-Query geparst.

## Permission (Berechtigung)

Ein String mit Namespace wie `admin.users.view`, `admin.articles.update`.
Wird bei jeder Action über die Middleware `AdminAccess` geprüft; Benutzer erhalten
Berechtigungen über `Role`s. Wildcards `*` und `admin.users.*` werden
unterstützt.

## Manifest

Das einzelne JSON-Dokument `/api/admin/system/manifest`, das beim Bootstrap an die
SPA zurückgegeben wird: `{version, locale, panel, resources, screens, settings,
dashboards, plugins, permissions}`. Das Frontend baut daraus die Vue-Router-Routen und
die Sidebar auf; Caching per ETag.

## Plugin

Eine Klasse, die `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin` implementiert —
host-seitig oder als Sister-Pack, die Resources/Screens/Settings/
Berechtigungen/Menüknoten über `register()` und `boot(Admin $admin)` beisteuert.

## Tenant (Mandant)

Optionaler Baustein für Mandantenfähigkeit (`TenantResolver`, `TenantContext`,
Trait `TenantScoped`). Die Auflösungsstrategie liegt beim Host; der Admin
stellt nur den Vertrag bereit.

## Widget / Dashboard

`Widget` — eine einzelne Dashboard-Kachel (`Stats`, `Chart`, `RecentList`,
`Heatmap`, `Gauge`, `Markdown`, `Iframe`, `Table`). `DashboardScreen` fasst
Widgets zusammen, mit optionalen Layout-Überschreibungen pro Benutzer. Dashboards liegen unter
`/dashboard/{slug}`.

## Settings

Ein einzeiliger Konfigurations-Screen (Singleton-artig). `SettingsResource`
definiert Felder und speichert Werte über `SettingsStorage` (Standard:
`KeyValueSettingsStorage` über die Tabelle `admin_settings`).

## Audit

Nur anhängendes Log der Admin-Aktionen (Modell `AuditLog` + Trait `Loggable`).
Dargestellt über das Layout `AuditTrail` und `AuditController`.

## Translatable

i18n auf Feldebene für Eloquent-Modelle, bereitgestellt von
`dskripchenko/laravel-translatable`. Der Admin bindet übersetzbare
Modelle über `TranslatableInput` (Tabs pro Locale) und
`TranslatableFieldBridge` an.

## Bootstrap

Initiale Payload, die die SPA vor dem Einbinden benötigt: CSRF-Token, Basis-URL,
Locale, Theme, Branding, aktueller Benutzer, Manifest-Version. Zwei Strategien:
`inline` (per Blade eingefügtes `<script>`, Standard) oder `xhr` (`/api/admin/system/bootstrap`).

## Siehe auch

- [English](../en/glossary.md)
- [Русский](../ru/glossary.md)
- [中文](../zh/glossary.md)

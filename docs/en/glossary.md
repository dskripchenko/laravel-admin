---
title: Glossary
audience: developer
status: stable
locale: en
---

# Glossary

Shared terminology across the entire `dskripchenko/laravel-admin` ecosystem
(`laravel-admin`, sister-packs, `@dskripchenko/ui`, `@dskripchenko/wysiwyg`).

## Resource

A class extending `Dskripchenko\LaravelAdmin\Resource\Resource`. Describes
how a single Eloquent model is exposed in the admin: fields (form),
columns (table), filters, actions, permissions. Backed by a `Repository`
and rendered through `GeneratedListScreen` / `GeneratedEditScreen` /
`GeneratedViewScreen`.

## Screen

An abstract page (Orchid-flavoured): a class extending
`Dskripchenko\LaravelAdmin\Screen\Screen` with `query()` (state),
`layout()` (renderable tree), `commandBar()` (actions). A *Custom Screen*
is anything not auto-generated from a `Resource` — registered via
`Admin::screen([...])`.

## Field

A form input descriptor: `Input`, `Textarea`, `Number`, `Select`,
`Wysiwyg`, `Repeater`, etc. Each field declares validation rules,
default value, visibility per mode (create/update/view), and an optional
type-specific config. Frontend renders a field through `FieldRenderer`
(JSON-driven).

## Layout

A renderable container that holds fields or other layouts: `Rows`,
`Columns`, `Tabs`, `Wizard` + `Step`, `Block`, `Modal`, `Drawer`,
`Wrapper`, `Infolist`, `View` (custom Vue component). Composable to
arbitrary depth.

## Action

A button/link/dropdown attached to a screen, row or bulk selection:
`Button`, `Link`, `BulkAction`, `ModalAction`, `DropDown`, `AsyncAction`.
Actions trigger a controller method (e.g. `Button::method('save')`).

## Filter

A table-filter descriptor for list-screens: `BaseInputFilter`,
`BaseDateFilter`, `BaseSwitcherFilter`, `BaseSelectFromModelFilter`,
`BaseSelectFromQueryFilter`, `BaseSelectFromOptionsFilter`,
`TrashedFilter`. Parsed from the HTTP query by `HttpFilterParser`.

## Permission

A namespaced string like `admin.users.view`, `admin.articles.update`.
Checked through `AdminAccess` middleware on every action; users hold
permissions via `Role`s. Wildcards `*` and `admin.users.*` are
supported.

## Manifest

The single JSON document `/api/admin/system/manifest` returned to the
SPA on bootstrap: `{resources, screens, settings, dashboards, plugins,
permissions, version}`. The frontend builds Vue Router routes and
sidebar from it; ETag-based caching.

## Plugin

A class implementing `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin` —
host-side or sister-pack contributing resources/screens/settings/
permissions/menu-nodes via `register()` and `boot(Admin $admin)`.

## Tenant

Optional multi-tenancy primitive (`TenantResolver`, `TenantContext`,
`TenantScoped` trait). Resolution strategy is host-side; the admin
provides only the contract.

## Widget / Dashboard

`Widget` — a single dashboard tile (`Stats`, `Chart`, `RecentList`,
`Heatmap`, `Gauge`, `Markdown`, `Iframe`). `DashboardScreen` aggregates
widgets with optional layout overrides per user. Dashboards live at
`/dashboard/{slug}`.

## Settings

A single-row configuration screen (singleton-style). `SettingsResource`
defines fields and persists values through `SettingsStorage` (default:
`KeyValueSettingsStorage` over the `admin_settings` table).

## Audit

Append-only log of admin actions (`AuditLog` model + `Loggable` trait).
Rendered via `AuditTrail` layout and `AuditController`.

## Translatable

Field-level i18n for Eloquent models, provided by
`dskripchenko/laravel-translatable`. The admin bridges translatable
models with `TranslatableInput` / `TranslatableField` (per-locale tabs).

## Bootstrap

Initial payload the SPA requires before mounting: CSRF token, base URL,
locale, theme, brand, current user, manifest version. Two strategies:
`inline` (Blade-injected `<script>`, default) or `xhr` (`/api/admin/system/bootstrap`).

## See also

- [Russian](../ru/glossary.md)
- [Deutsch](../de/glossary.md)
- [中文](../zh/glossary.md)

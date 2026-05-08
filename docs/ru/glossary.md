---
title: Глоссарий
audience: developer
status: stable
locale: ru
translated_from: en/glossary.md
translated_at: 2026-05-08
---

# Глоссарий

Единые термины всей экосистемы `dskripchenko/laravel-admin`
(`laravel-admin`, sister-пакеты, `@dskripchenko/ui`,
`@dskripchenko/wysiwyg`).

## Resource (Ресурс)

Класс, наследующий `Dskripchenko\LaravelAdmin\Resource\Resource`.
Описывает, как одна Eloquent-модель представлена в админке: поля
(форма), колонки (таблица), фильтры, действия, разрешения. Работает
через `Repository` и рендерится через `GeneratedListScreen` /
`GeneratedEditScreen` / `GeneratedViewScreen`.

## Screen (Экран)

Абстрактная страница (в стиле Orchid): класс, наследующий
`Dskripchenko\LaravelAdmin\Screen\Screen`, с методами `query()`
(состояние), `layout()` (дерево layout/field), `commandBar()`
(действия). *Кастомный Screen* — любая страница, не сгенерированная
автоматически из `Resource`. Регистрируется через `Admin::screen([...])`.

## Field (Поле)

Дескриптор формы: `Input`, `Textarea`, `Number`, `Select`, `Wysiwyg`,
`Repeater` и т.д. У каждого поля — правила валидации, значение по
умолчанию, видимость по режимам (create/update/view) и опциональная
type-specific конфигурация. Frontend рендерит поле через `FieldRenderer`
(JSON-driven).

## Layout

Renderable-контейнер, содержащий поля или другие layout'ы: `Rows`,
`Columns`, `Tabs`, `Wizard` + `Step`, `Block`, `Modal`, `Drawer`,
`Wrapper`, `Infolist`, `View` (произвольный Vue-компонент).
Композируется на любую глубину.

## Action (Действие)

Кнопка/ссылка/dropdown в commandBar, строке или bulk-выделении:
`Button`, `Link`, `BulkAction`, `ModalAction`, `DropDown`,
`AsyncAction`. Действие триггерит метод контроллера (например
`Button::method('save')`).

## Filter (Фильтр)

Дескриптор фильтра для list-страниц: `BaseInputFilter`,
`BaseDateFilter`, `BaseSwitcherFilter`, `BaseSelectFromModelFilter`,
`BaseSelectFromQueryFilter`, `BaseSelectFromOptionsFilter`,
`TrashedFilter`. Парсится из HTTP-query через `HttpFilterParser`.

## Permission (Разрешение)

Строка вида `admin.users.view`, `admin.articles.update`. Проверяется
middleware'ом `AdminAccess` на каждое действие. Пользователи получают
разрешения через `Role`. Поддерживаются wildcard'ы `*` и
`admin.users.*`.

## Manifest (Манифест)

Единый JSON-документ `/api/admin/system/manifest`, отдаваемый SPA на
bootstrap: `{resources, screens, settings, dashboards, plugins,
permissions, version}`. Frontend строит из него Vue Router маршруты и
sidebar; кэширование через ETag.

## Plugin (Плагин)

Класс, реализующий `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin` —
host-side или sister-pack, добавляющий resource/screen/settings/
permissions/menu через `register()` и `boot(Admin $admin)`.

## Tenant

Опциональный примитив multi-tenancy (`TenantResolver`, `TenantContext`,
trait `TenantScoped`). Стратегия резолвинга — на стороне host'а; admin
предоставляет только контракт.

## Widget / Dashboard

`Widget` — одна плитка дашборда (`Stats`, `Chart`, `RecentList`,
`Heatmap`, `Gauge`, `Markdown`, `Iframe`). `DashboardScreen` агрегирует
виджеты с per-user layout-override'ами. Дашборды — на URL
`/dashboard/{slug}`.

## Settings (Настройки)

Singleton-страница конфигурации. `SettingsResource` объявляет поля,
значения хранит через `SettingsStorage` (по умолчанию:
`KeyValueSettingsStorage` поверх таблицы `admin_settings`).

## Audit (Аудит)

Append-only журнал действий администраторов (модель `AuditLog` + trait
`Loggable`). Отображается через layout `AuditTrail` и
`AuditController`.

## Translatable

Field-level i18n для Eloquent-моделей через
`dskripchenko/laravel-translatable`. Admin интегрируется с
translatable-моделями через `TranslatableInput` / `TranslatableField`
(табы по локалям).

## Bootstrap

Стартовый payload для SPA: CSRF-токен, base URL, locale, theme, brand,
текущий пользователь, версия manifest'а. Две стратегии: `inline`
(Blade-injected `<script>`, default) или `xhr`
(`/api/admin/system/bootstrap`).

## Смотрите также

- [English](../en/glossary.md)
- [Deutsch](../de/glossary.md)
- [中文](../zh/glossary.md)

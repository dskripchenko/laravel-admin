---
title: 术语表
audience: developer
status: stable
locale: zh
translated_from: en/glossary.md
translated_at: 2026-05-08
---

# 术语表

整个 `dskripchenko/laravel-admin` 生态系统通用的术语
（`laravel-admin`、姐妹包、`@dskripchenko/ui`、
`@dskripchenko/wysiwyg`）。

## Resource (资源)

继承 `Dskripchenko\LaravelAdmin\Resource\Resource` 的类。描述单个
Eloquent 模型在管理后台中如何呈现：字段（表单）、列（表格）、过滤器、
动作、权限。通过 `Repository` 操作，使用 `GeneratedListScreen` /
`GeneratedEditScreen` / `GeneratedViewScreen` 渲染。

## Screen (屏幕)

抽象页面（Orchid 风格）：继承
`Dskripchenko\LaravelAdmin\Screen\Screen` 的类，包含 `query()`
（状态）、`layout()`（可渲染树）、`commandBar()`（动作）。*自定义
Screen* 是任何不是从 `Resource` 自动生成的页面 — 通过
`Admin::screen([...])` 注册。

## Field (字段)

表单输入描述符：`Input`、`Textarea`、`Number`、`Select`、`Wysiwyg`、
`Repeater` 等。每个字段声明验证规则、默认值、按模式
（create/update/view）的可见性，以及可选的类型特定配置。前端通过
`FieldRenderer`（JSON 驱动）渲染字段。

## Layout (布局)

包含字段或其他布局的可渲染容器：`Rows`、`Columns`、`Tabs`、`Wizard` +
`Step`、`Block`、`Modal`、`Drawer`、`Wrapper`、`Infolist`、`View`
（自定义 Vue 组件）。可任意深度组合。

## Action (动作)

附加到 Screen、行或批量选择的按钮/链接/下拉菜单：`Button`、`Link`、
`BulkAction`、`ModalAction`、`DropDown`、`AsyncAction`。动作触发控制
器方法（例如 `Button::method('save')`）。

## Filter (过滤器)

list-screen 的表格过滤器描述符：`BaseInputFilter`、`BaseDateFilter`、
`BaseSwitcherFilter`、`BaseSelectFromModelFilter`、
`BaseSelectFromQueryFilter`、`BaseSelectFromOptionsFilter`、
`TrashedFilter`。由 `HttpFilterParser` 从 HTTP 查询中解析。

## Permission (权限)

命名空间字符串，如 `admin.users.view`、`admin.articles.update`。在每
个动作上通过 `AdminAccess` 中间件检查；用户通过 `Role` 持有权限。支
持通配符 `*` 和 `admin.users.*`。

## Manifest (清单)

`/api/admin/system/manifest` 在 SPA 启动时返回的单个 JSON 文档：
`{resources, screens, settings, dashboards, plugins, permissions,
version}`。前端从中构建 Vue Router 路由和侧边栏；基于 ETag 的缓存。

## Plugin (插件)

实现 `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin` 的类 — 主机端
或姐妹包，通过 `register()` 和 `boot(Admin $admin)` 贡献
resources/screens/settings/permissions/menu-nodes。

## Tenant (租户)

可选的多租户原语（`TenantResolver`、`TenantContext`、`TenantScoped`
trait）。解析策略由主机端决定；admin 仅提供契约。

## Widget / Dashboard

`Widget` — 单个仪表板瓦片（`Stats`、`Chart`、`RecentList`、
`Heatmap`、`Gauge`、`Markdown`、`Iframe`）。`DashboardScreen` 聚合
widgets 并支持每用户的布局覆盖。仪表板位于 `/dashboard/{slug}`。

## Settings (设置)

单行配置屏幕（singleton 风格）。`SettingsResource` 定义字段，通过
`SettingsStorage`（默认：`admin_settings` 表上的
`KeyValueSettingsStorage`）持久化值。

## Audit (审计)

管理员动作的只追加日志（`AuditLog` 模型 + `Loggable` trait）。通过
`AuditTrail` 布局和 `AuditController` 渲染。

## Translatable (可翻译)

由 `dskripchenko/laravel-translatable` 提供的 Eloquent 模型字段级
i18n。Admin 通过 `TranslatableInput` / `TranslatableField`（按 locale
分标签）连接 translatable 模型。

## Bootstrap (启动)

SPA 挂载前所需的初始 payload：CSRF token、base URL、locale、theme、
brand、当前用户、manifest 版本。两种策略：`inline`（Blade 注入的
`<script>`，默认）或 `xhr`（`/api/admin/system/bootstrap`）。

## 另请参阅

- [English](../en/glossary.md)
- [Русский](../ru/glossary.md)
- [Deutsch](../de/glossary.md)

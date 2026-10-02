---
title: 术语表
audience: developer
status: stable
locale: zh
translated_from: en/glossary.md
translated_at: 2026-10-02
---

# 术语表

整个 `dskripchenko/laravel-admin` 生态系统通用的术语（`laravel-admin`、姐妹包、
`@dskripchenko/ui`、`@dskripchenko/wysiwyg`）。

## Resource（资源）

继承 `Dskripchenko\LaravelAdmin\Resource\Resource` 的类。描述单个 Eloquent 模型在
管理面板中如何呈现：字段（表单）、列（表格）、过滤器、Action、权限。通过
`GeneratedListScreen`（层级结构的 Resource 则为 `GeneratedTreeScreen`）/
`GeneratedCreateScreen` / `GeneratedEditScreen` / `GeneratedViewScreen` 渲染。

## Screen

一种抽象页面（Orchid 风格）：继承 `Dskripchenko\LaravelAdmin\Screen\Screen` 的类，
包含 `query()`（state）、`layout()`（可渲染树）、`commandBar()`（Action）。
*自定义 Screen* 指任何不是从 `Resource` 自动生成的页面——通过 `Admin::screen([...])`
注册。

## Field（字段）

表单输入描述符：`Input`、`Textarea`、`Number`、`Select`、`Wysiwyg`、`Repeater` 等。
每个字段声明校验规则、默认值、按模式（create/update/view）的可见性，以及可选的类型
特定配置。前端通过 `FieldRenderer`（JSON 驱动）渲染字段。

## Layout（布局）

包含字段或其他布局的可渲染容器：`Rows`、`Columns`、`Tabs`、`Wizard` + `Step`、
`Block`、`Modal`、`Drawer`、`Wrapper`、`Infolist`、`View`（自定义 Vue 组件）。可以
任意深度组合。

## Action（动作）

附加到 Screen、表格行或批量选择上的按钮/链接/下拉菜单：`Button`、`Link`、
`BulkAction`、`ModalAction`、`DropDown`、`AsyncAction`。Action 会触发 Screen 或
Resource 上的方法（例如 `Button::make('Save')->method('save')`）。

## Filter（过滤器）

列表 Screen 的表格过滤器描述符，通过 `::for('column')` 创建：`InputFilter`、
`OptionsFilter`、`DateRangeFilter`、`SwitcherFilter`、`SelectFromModelFilter`、
`QueryFilter`、`TrashedFilter`。由 `HttpFilterParser` 从 HTTP 查询中解析。

## Permission（权限）

带命名空间的字符串，如 `admin.users.view`、`admin.articles.update`。每个 Action 都会
通过 `AdminAccess` 中间件进行检查；用户通过 `Role` 持有权限。支持通配符 `*` 和
`admin.users.*`。

## Manifest（清单）

SPA 启动时由 `/api/admin/system/manifest` 返回的单个 JSON 文档：`{version, locale,
panel, resources, screens, settings, dashboards, plugins, permissions}`。前端据此构建
Vue Router 路由和侧边栏；基于 ETag 缓存。

## Plugin（插件）

实现 `Dskripchenko\LaravelAdmin\Plugin\AdminPlugin` 的类——来自宿主侧或姐妹包，通过
`register()` 和 `boot(Admin $admin)` 提供 resources/screens/settings/
permissions/menu-nodes。

## Tenant（租户）

可选的多租户原语（`TenantResolver`、`TenantContext`、`TenantScoped` trait）。解析策略
由宿主侧决定；管理面板只提供契约。

## Widget / Dashboard（小部件 / 仪表板）

`Widget`——单个仪表板磁贴（`Stats`、`Chart`、`RecentList`、`Heatmap`、`Gauge`、
`Markdown`、`Iframe`、`Table`）。`DashboardScreen` 汇集小部件，并支持按用户覆盖布局。
仪表板位于 `/dashboard/{slug}`。

## Settings（设置）

单行配置 Screen（单例风格）。`SettingsResource` 定义字段，并通过 `SettingsStorage`
持久化值（默认：基于 `admin_settings` 表的 `KeyValueSettingsStorage`）。

## Audit（审计）

管理员操作的只追加日志（`AuditLog` 模型 + `Loggable` trait）。通过 `AuditTrail` 布局和
`AuditController` 渲染。

## Translatable（可翻译）

Eloquent 模型的字段级 i18n，由 `dskripchenko/laravel-translatable` 提供。管理面板通过
`TranslatableInput`（按 locale 分标签页）和 `TranslatableFieldBridge` 对接可翻译模型。

## Bootstrap（启动载荷）

SPA 挂载前所需的初始载荷：CSRF 令牌、基础 URL、locale、主题、品牌、当前用户、
manifest 版本。两种策略：`inline`（由 Blade 注入的 `<script>`，默认）或 `xhr`
（`/api/admin/system/bootstrap`）。

## 另请参阅

- [English](../en/glossary.md)
- [Русский](../ru/glossary.md)
- [Deutsch](../de/glossary.md)

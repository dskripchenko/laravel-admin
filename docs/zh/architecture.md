---
title: 架构
audience: developer
status: stable
locale: zh
translated_from: en/architecture.md
translated_at: 2026-10-02
---

# 架构

本文描述 `laravel-admin` 的高层设计。包含每项决策背后理由的详细设计文档位于仓库中的
`docs/internal/architecture.md`（俄文，约 1500 行）。

## 目标

1. **Resource 优先。** 管理面板中的大多数页面都是 CRUD。将一个 Eloquent 模型声明为
   `Resource`，即可免费获得列表、创建、编辑、查看。
2. **超越 CRUD 的可组合性。** 提供 `Screen`/`Layout`/`Field`/`Action`
   原语，使非 CRUD 页面（表单、报表、仪表板）复用同一条渲染管线。
3. **JSON 驱动的 SPA。** 由 `@dskripchenko/laravel-
   admin` 提供的单一 bundle，从 manifest（清单）完成 hydration。宿主编写 PHP，
   SPA 负责渲染。不会有两个管理面板以相同方式连接。
4. **无厂商锁定。** 编辑器、图表、文件存储、队列都是可插拔的。姐妹包是可选的。
5. **为多租户做好准备。** 租户解析在宿主侧完成。我们提供契约
   （`TenantResolver`/`TenantContext`/`TenantScoped`）。

## 高层流程

```
HTTP request (Laravel)
  └── AdminApiModule (laravel-api) — RunVersionMiddleware picks the stack per request:
      the panel stack for `admin` and the panels, the BaseApi's own for a host's versions
       └── AdminApi::getMethods()
            ├── system / auth / profile / dashboard / audit / ...
            ├── resources (compiled per-Resource via ResourceCompiler)
            ├── settings (compiled per-Resource via SettingsCompiler)
            └── screens (compiled per-Screen via ScreenCompiler)
                  └── ScreenController::state / runMethod
                        └── Screen::compile() → {state, layout, command_bar, ...}

SPA bootstrap
  └── createAdminApp(bootstrap)
       ├── createAdminClient (axios)
       ├── manifestStore.load() ← /api/admin/system/manifest
       ├── menuStore.load()     ← /api/admin/system/menu
       └── replaceManifestRoutes ← Vue Router built from manifest

User navigates to /admin/r/{slug} (Resource list)
  └── ResourceIndexPage
       └── useResourceIndexStore.load() → POST /{slug}/search
            └── Manifest's columns + filters + actions
```

## PHP 层

| 层 | 内容 | 文件 |
|---|---|---|
| `Admin` | 管理器门面。`resources/screen/menu/...` 的入口点。 | `src/Admin.php` |
| `Resource` | 模型包装器：字段/列/过滤器/Action。 | `src/Resource/Resource.php` |
| `Screen` | 抽象页面（`query`/`layout`/`commandBar`）。 | `src/Screen/Screen.php` |
| `Field` | 表单输入描述符。 | `src/Field/*` |
| `Layout` | 可渲染容器（Rows/Columns/Tabs/...）。 | `src/Layout/*` |
| `Action` | Button/Link/Bulk/Modal/Async。 | `src/Action/*` |
| `Filter` | 表格过滤器。 | `src/Filter/*` |
| `Widget` | 仪表板磁贴。 | `src/Widget/*` |
| `MenuNode/MenuRegistry` | 层级侧边栏树。 | `src/Menu/*` |
| `Permission` | RBAC（Role/Permission、AdminAccess 中间件）。 | `src/Permission/*` |
| `Audit` | `AuditLog` + `Loggable` trait。 | `src/Audit/*` |
| `Settings` | 单例配置 Screen。 | `src/Settings/*` |
| `Tenancy` | Resolver/Context 契约。 | `src/Tenancy/*` |
| `Plugin` | `AdminPlugin` 接口、`PluginRegistry`。 | `src/Plugin/*` |
| `Theme/I18n` | ThemeManager、LocaleResolver。 | `src/Theme/*`、`src/I18n/*` |
| `Http/AdminApi` | 将上述所有内容映射到供 laravel-api 使用的 `getMethods()`。 | `src/Http/AdminApi.php` |

## 前端层

| 层 | 内容 | 路径 |
|---|---|---|
| `createAdminApp` | 入口：客户端、store、路由、注册表、挂载。 | `resources/ts/createAdminApp.ts` |
| Store（Pinia） | auth/manifest/menu/theme/locale/notifications/resourceIndex/resourceForm/screen/dashboard。 | `resources/ts/stores/*` |
| 路由 | `buildRoutesFromManifest` + auth-guard + title-guard。 | `resources/ts/router/*` |
| 渲染 | `FieldRenderer`、`LayoutRenderer`、`WidgetRenderer`、`provideFormState`。 | `resources/ts/components/render/*` |
| 页面 | `HomePage`、`ResourceIndexPage`、`ResourceFormPage`、`ResourceViewPage`、`ScreenPage`、`DashboardPage`、`ProfilePage`、`ImportWizardPage`、`FieldGalleryPage`。 | `resources/ts/components/*` |
| Shell | `AdminApp`、`AdminTopBar`、`AdminSidebar`、`AdminSidebarNode`、`BrandLogo`、`NotificationsDrawer`。 | `resources/ts/components/shell/*` |

## 关键契约

### Manifest

SPA 的唯一事实来源，由 `/api/admin/system/manifest` 返回：

```json
{
  "version": "3f9a0c…",
  "locale": "en",
  "panel": "admin",
  "resources": [{ "slug": "articles", "label": "Articles", "fields": [...], "columns": [...], ... }],
  "screens":   [{ "slug": "contact", "name": "Contact", "permission": null }],
  "settings":  [{ "slug": "brand", "fields": [...], ... }],
  "dashboards":[{ "slug": "content", "label": "Analytics", "widgets": [...] }],
  "plugins":   [...],
  "permissions": []
}
```

通过 ETag 缓存（`If-None-Match` / `304`）。

### Screen::compile()

适用于任何 Screen（自动生成的或自定义的）的通用载荷：

```json
{
  "state": { "form_field": "value", ... },
  "name": "Contact",
  "description": "Reach the team",
  "layout": [ { "type": "rows", "children": [ { "kind": "field", ... } ] } ],
  "command_bar": [ { "kind": "action", "type": "button", "name": "send", ... } ],
  "permissions": [],
  "etag": "0123abcd..."
}
```

### Action 分发

Action 会调用 PHP 侧的某个方法。`Screen` 的 Action 发往
`ScreenController::runMethod`，并指明该 Screen 的一个公共方法：

```json
{ "method": "send", "payload": { "form_field": "value" } }
```

`Resource` 的 Action 发往 `ResourceController::action`，通过键指明 Action，
并携带选中的记录；Resource 的方法以
`$resource->{method}(array $ids, array $payload)` 的形式调用：

```json
{ "key": "publish", "ids": [1, 2], "payload": {} }
```

两者都返回规范化的 `payload` 结构（`message`、`alerts`、`state`、
`refresh`、`redirect_url`、`download_url`）。

## 权限模型

- 权限是一个字符串：`admin.{resource}.{action}`（例如
  `admin.articles.update`）。
- 通配符：`admin.articles.*`、`*`。
- 角色持有权限列表（`Role::permissions = ['*']`）。
- 用户持有角色。`User::hasAccess($p)` 进行传递性检查。
- `AdminAccess` 中间件在每个 Action 上强制执行检查。
- `ResourceCompiler` 会自动为每个生成的路由附加正确的
  `AdminAccess:{permission}`。

## 可插拔的部分

| 部分 | 默认 | 如何覆盖 |
|---|---|---|
| WYSIWYG | `@dskripchenko/wysiwyg` | `registerField('wysiwyg', QuillField)` |
| 文件存储 | `config('admin.uploads.disk')`（`ADMIN_UPLOADS_DISK`，默认 `local`） | 配置 / 宿主的磁盘 |
| PDF 渲染 | mPDF 或 dompdf，取决于安装了哪一个 | `admin.exports.pdf.driver` = `mpdf` / `dompdf` |
| 图表 | 内置 SVG 小部件 | `registerWidget('chart', MyChart)` |
| 认证 guard | `auth.guard = admin` | 配置 |
| 用户模型 | `AdminUser` | 宿主的 `Authenticatable` |
| locale 来源 | 6 步的 `LocaleResolver` | `admin.ui.default_locale` 及其他配置 |

## 另请参阅

- [术语表](glossary.md)——术语
- [API 参考](api-reference.md)——REST 端点
- [前端扩展](frontend-extension.md)——宿主侧的自定义组件

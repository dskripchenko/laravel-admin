# dskripchenko/laravel-admin

> 🌐 [English](README.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · **中文**

受 Orchid 启发的 Laravel 管理面板构造器，配备 Vue 3 SPA 前端。

[![npm](https://img.shields.io/npm/v/@dskripchenko/laravel-admin?label=%40dskripchenko%2Flaravel-admin)](https://www.npmjs.com/package/@dskripchenko/laravel-admin)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin)](https://packagist.org/packages/dskripchenko/laravel-admin)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin)](LICENSE)

```php
Admin::resources([UserResource::class, ArticleResource::class]);
Admin::screen([ContactScreen::class, SystemStatusScreen::class]);
Admin::menu()->add(
    MenuNode::make('content', '内容')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::dashboard('analytics'),
    ]),
);
```

## 内含功能

- **CRUD 流水线** — 将 Eloquent 模型声明为 `Resource`，自动获得
  list/create/edit/view 屏幕。
- **自定义 Screen** — 通过 `Admin::screen()` 实现非 CRUD 页面（表单、
  报表、仪表板）。处理 state、layout、command-bar、验证、权限。
- **层级菜单** — 流式 API `Admin::menu()->add(MenuNode::...)`，任意深
  度，自动解析 `resource()`/`screen()`/`dashboard()`。
- **30+ 字段类型** — Input/Number/Select/Combobox/DatePicker/
  ColorPicker/FileUpload/Wysiwyg/Markdown/TranslatableInput/Repeater/
  RelationSelect/Cascader/TreeSelect/Slug/KeyValue/TagsInput/...
- **15+ 布局** — Rows/Columns/Tabs/Wizard+Step/Block/Modal/Drawer/
  Wrapper/Infolist/Dashboard/Accordion/View/...
- **表格** — 可排序列、preset、过滤器、行内编辑、汇总、保存视图、
  分组、轮询、导出（CSV/XLSX/PDF）。
- **仪表板** — 8 种小部件类型（Stats/Chart/RecentList/Markdown/
  Iframe/Table/Heatmap/Gauge），每用户布局覆盖、拖动/调整大小、轮询。
- **认证 & RBAC** — 多 guard、AdminUser、Roles、2FA TOTP、profile、
  模拟、密码重置、邮箱验证。
- **审计** — 管理员动作的只追加日志（`AuditLog` + `Loggable` trait）。
- **设置** — 单例配置屏幕。
- **通知** — 铃铛徽章 + 抽屉（Database notifications）。
- **API tokens** — Profile 中的 Sanctum 集成（可选）。
- **主题** — 浅色/深色 + 用户偏好，`@dskripchenko/ui` 设计 token。
- **i18n** — locale 解析器（5 步优先级），与
  `dskripchenko/laravel-translatable` 的 `TranslatableField` 桥接。
- **多租户** — `TenantResolver` / `TenantContext` / `TenantScoped`
  trait。策略由主机端决定；我们仅提供契约。
- **插件** — `AdminPlugin` 接口；姐妹包使用相同的 hook。
- **测试** — `ResourceTestCase`、`ScreenTestCase`、`ActsAsAdmin` trait。
- **OpenAPI 3.0** — 从 docblock 标签 `@input`/`@output` 生成。

## 安装

```bash
composer require dskripchenko/laravel-admin
php artisan vendor:publish --tag=admin-config
php artisan migrate
```

```js
// resources/js/admin.js
import { createAdminApp } from '@dskripchenko/laravel-admin'
import '@dskripchenko/ui/styles/all.css'
import '@dskripchenko/laravel-admin/style.css'

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
app.mount('#admin-app')
```

```bash
npm i @dskripchenko/laravel-admin @dskripchenko/ui
npm run build
```

访问 `/admin/login`。第一个 resource 见
[getting-started.md](docs/zh/getting-started.md)。

## 文档

- [快速开始](docs/zh/getting-started.md)
- [架构](docs/zh/architecture.md)
- 概念: [Resources](docs/zh/concepts/resources.md) ·
  [Screens](docs/zh/concepts/screens.md) ·
  [Widgets & Dashboards](docs/zh/concepts/widgets-and-dashboards.md) ·
  [菜单](docs/zh/concepts/menu.md) ·
  [Actions](docs/en/concepts/actions.md) (en) ·
  [权限](docs/en/concepts/permissions.md) (en) ·
  [i18n](docs/en/concepts/i18n.md) (en) ·
  [租户](docs/en/concepts/tenancy.md) (en)
- [字段参考](docs/en/fields-reference.md) (en)
- [布局参考](docs/en/layouts-reference.md) (en)
- [API 参考](docs/en/api-reference.md) (en)
- [前端扩展](docs/en/frontend-extension.md) (en)
- [测试](docs/en/testing.md) (en)
- [迁移指南](docs/en/migration-guide.md) (en)
- [术语表](docs/zh/glossary.md)

## 技术栈

- **PHP** ^8.5
- **Laravel** ^12
- **Vue** ^3.4 + TypeScript + Pinia + Vue Router
- **Bundle** — `@dskripchenko/laravel-admin` ~62 KB gz (esm + cjs)
- **无 vendor lock-in** 用于编辑器/图表 — 自带（姐妹包适配器：
  `quill`、`tinymce`）

## 姐妹包

可选扩展，仅安装所需：

| 包 | 用途 |
|---|---|
| `dskripchenko/laravel-admin-starter` | User/Role/Audit/Settings/Translations/Blocks resources |
| `dskripchenko/laravel-admin-tinymce` | TinyMCE WYSIWYG 适配器 |
| `dskripchenko/laravel-admin-quill` | Quill WYSIWYG 适配器 |
| `dskripchenko/laravel-admin-search` | ⌘K 命令面板 + Scout suggest |
| `dskripchenko/laravel-admin-media` | 媒体库（无 Spatie/medialibrary 依赖） |
| `dskripchenko/laravel-admin-health` | 健康检查（无 Spatie/laravel-health 依赖） |
| `dskripchenko/laravel-admin-pulse` | 遥测（无 laravel/pulse 依赖） |
| `dskripchenko/laravel-admin-jobs` | Failed jobs / batches 查看器 |

## 贡献

参见 [CONTRIBUTING.md](CONTRIBUTING.md)。欢迎 PR。

## 许可证

[MIT](LICENSE) © Denis Skripchenko

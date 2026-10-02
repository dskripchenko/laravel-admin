---
title: 迁移指南
audience: developer
status: stable
locale: zh
translated_from: en/migration-guide.md
translated_at: 2026-10-02
---

# 迁移指南

主版本和次版本之间的升级。我们遵循 [SemVer](https://semver.org)。

## 1.4.x → 1.36.x

目前还没有 2.0，这些版本也都不需要重写代码，但其中有几个改变了宿主可能依赖的行为。
按从新到旧排列：

- **1.36.0**——需要 `dskripchenko/laravel-api` `^5.11`。
- **1.34.0**——需要 `@dskripchenko/ui` `^1.4.0`（仅对自定义构建有影响）。`order`
  相同的菜单项保持其添加顺序，而不再按字母排序。控制台命令（`admin:user`、
  `admin:make-*`）的输出改为英文。
- **1.33.0**——`admin:install` 会把预构建的前端发布到 `public/vendor/admin`；除非你要
  注册自己的 Vue 组件，否则不再需要 Node。默认的管理面板 locale 跟随
  `config('app.locale')`，除非设置了 `ADMIN_LOCALE` / `admin.ui.default_locale`
  （以前默认是俄语）——如果你依赖旧的默认值，请显式设置它。
- **1.32.0**——与管理面板拼接在一起的宿主 API 版本（`parent::getApiVersionList()`）
  不再继承管理面板的中间件栈。如果你的某个版本依赖 `AdminLocale` 或由它带来的会话，
  请在该版本的 `getMethods()` 中声明它们。
- **1.27.0**——个人资料中的“API 令牌”和“会话”标签页仅在宿主填充了相应插槽时显示。
- **1.17.0**——已保存的列表视图改为按需启用：在使用它们的 Resource 上让
  `Resource::savedViews()` 返回 `true`；`{slug}_views/*` 路由只为这些 Resource 注册。
- **1.16.0**——`{slug}/exportCsv` Action 已移除；请调用 `{slug}/export?format=csv`。
- **1.15.0**——Resource 的 Action 按能力注册：`tree` 和 `treeScreen` 仅用于层级结构的
  Resource，`restore` 和 `forceDelete` 仅在带 `SoftDeletes` 时，`replicate` 和
  `reorder` 仅在 `replicable()` / `reorderable()` 时。不支持的 Action 返回 404，
  而不是 409/422。
- **1.7.0**——支持矩阵为 PHP 8.2–8.5 和 Laravel 11/12/13；
  `dskripchenko/laravel-api` `^5.0`。
- **1.5.6**——在没有自定义 `infolist()` 的查看页上，`Switcher` 字段渲染为“是/否”
  `IconEntry`，而不是原始的 `true`/`false`。

升级后运行 `php artisan migrate`：新的表和列以迁移的形式提供。其余内容请参阅
[`CHANGELOG.md`](../../CHANGELOG.md)。

## 1.3.x → 1.4.0

**没有破坏性变更。** 新功能都需要主动启用。值得注意的新增内容：

### 新增：层级菜单（M1+M2）

```php
Admin::menu()->add(
    MenuNode::make('shop', '商店')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
```

如果你不调用 `Admin::menu()`，则保留 1.2.x 的自动填充行为。

### 新增：自定义 Screen（P21+P22）

```php
class ContactScreen extends Screen { /* ... */ }

Admin::screen([ContactScreen::class]);
```

URL：`/admin/screens/contact`。参见
[concepts/screens.md](concepts/screens.md)。

### 新增：小部件轮询与 rowSpan

```php
StatsOverviewWidget::make()
    ->title('实时')
    ->refresh(30)        // 每 30 秒轮询一次
    ->rowSpan(2);        // 占 2 个网格行高（1.4.0）
```

`refresh()` 在 1.2.x 中就已存在，但前端忽略了它。现在它会在 `/dashboard/widgets`
上触发 `setInterval`。

### 前端：WidgetRenderer 属性过滤

如果你编写过访问 `props.size`（以像素为单位）的自定义小部件 Vue 组件，你需要添加自己的
像素尺寸属性——之前的 `size` 属性表示网格列跨度（1..12），而现在通过剔除仪表板元数据
字段，消除了跨度与像素之间的歧义。参见
[frontend-extension.md → 自定义小部件](frontend-extension.md#自定义小部件)。

## 1.2.x → 1.3.0

作为自定义 Screen API 的首个版本发布。1.4.0 的所有变更都从这里开始；我们建议直接升级到
1.4.0（无破坏性变更）。

## 1.1.x → 1.2.0

### 新增：仪表板小部件

引入了 `Widget::class`、`DashboardScreen::class`。姐妹包没有升级版本——它们在
`^1.2.0` 下继续正常工作。

### 新增：2FA TOTP

`two_factor_secret`、`two_factor_recovery_codes` 和 `two_factor_confirmed_at` 列通过
迁移添加到 `admin_users`。升级后运行 `php artisan migrate`。

### 铃铛通知

`SystemController::me` 现在返回 `unread_notifications_count`。如果你覆盖了 `me` 载荷，
请合并这个新字段。

## 1.0.x → 1.1.0

（稳定版之前，没有发布过 1.0.x 版本。1.1.0 是第一个发布的稳定版。）

## 升级检查清单（通用）

对于任何次版本/补丁版本升级：

```bash
composer update dskripchenko/laravel-admin
php artisan migrate
php artisan admin:publish        # 重新发布预构建的前端（composer 钩子也会执行此操作）
php artisan optimize:clear
```

如果使用自己的构建（`admin:install --custom-build`），请更新 npm 包并重新构建，
而不是运行 `admin:publish`：

```bash
npm update @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

包的配置与你的配置按一层深度合并：在你发布过的配置块内部（比如 `auth` 内部）新增的键，
只有在你手动复制过去之后才会生效——当某个版本提到配置变更时，请与
`vendor/dskripchenko/laravel-admin/config/admin.php` 进行对比。

对于主版本升级，还需要：

1. 从头到尾阅读本指南中的相关章节。
2. 浏览 `CHANGELOG.md` 中的破坏性变更。
3. 部署前运行完整的测试套件。

## 另请参阅

- [`CHANGELOG.md`](../../CHANGELOG.md)
- [架构](architecture.md)
- [将管理面板集成到现有应用 → 升级](integration.md#8-升级与故障排查)

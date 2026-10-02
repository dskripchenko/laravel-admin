---
title: 快速开始
audience: developer
status: stable
locale: zh
translated_from: en/getting-started.md
translated_at: 2026-10-02
---

# 快速开始

本文将带您在大约十分钟内，从一个全新的 Laravel 应用走到一个可用的管理面板，
并包含一个自定义 Resource。

## 前置条件

- PHP 8.2+
- Laravel 11、12 或 13
- Node —— 仅在您自行构建前端时需要（`--custom-build`）
- 一个您想要管理的 Eloquent 模型（我们将使用 `Article`）

## 安装

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install
```

`admin:install` 会发布 `config/admin.php` 和迁移文件，将预构建前端发布到
`public/vendor/admin`，执行 `migrate` 并创建第一个管理员。它会创建
`admin_users`、`admin_roles`、`admin_settings`、`admin_audit_logs`、
`admin_dashboard_layouts` 以及另外几张表。

要把管理面板添加到一个已经有用户的应用中？使用
`php artisan admin:install --shared`，用户即可使用他们平常的账号登录，
而不是使用单独的 `admin_users` 表——参见
[将管理面板添加到现有应用](integration.md)。

不需要 Node，也不需要构建步骤：包中自带已构建好的管理 SPA。`admin:install`
还会提议把 `php artisan admin:publish` 添加到 composer 的 `post-update-cmd`
中，这样每次包更新时前端都会被重新发布（当已发布的副本过期时，管理面板会显示警告）。

选项：`--no-migrate`、`--no-user`、`--no-composer-hook`、`--force`
（覆盖已发布的文件）、`--custom-build`（见下文）。

### 自定义字段、小部件或页面：您自己的构建

要注册您自己的 Vue 组件，请使用应用的 Vite 来构建管理面板，而不是使用预构建包：

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

`--custom-build` 会创建 `resources/js/admin.js`，将其添加到 `vite.config.js`
中 `laravel-vite-plugin` 的输入项里，并让 `config('admin.assets')` 指向 Vite
manifest。请在该入口中、`createAdminApp()` 之前注册您的组件——参见
[前端扩展](frontend-extension.md)。

## 创建第一个管理员用户

如果您在 `admin:install` 时跳过了这一步，或者需要更多管理员：

```bash
php artisan admin:user --super
```

它会询问姓名、邮箱和密码（也可以作为参数传入：
`admin:user "Admin" admin@example.com secret123 --super`）。`--super`
会授予拥有全部权限的角色。

使用这些凭据访问 `/admin/login`。该路径来自 `ADMIN_PATH`（默认为 `admin`）。

## 您的第一个 Resource

使用交互式向导生成骨架——它会询问标签、模型（或数据表）、表单字段和表格列、
权限以及图标：

```bash
php artisan admin:make-resource
```

或者手动编写：

```php
namespace App\Admin\Resources;

use App\Models\Article;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;
    public static string $icon  = 'file-text';

    public static function label(): string { return '文章'; }

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Input::make('slug')->required(),
            Textarea::make('excerpt')->rows(3),
            Select::make('status')->options([
                'draft' => '草稿',
                'review' => '审核中',
                'published' => '已发布',
                'archived' => '已归档',
            ])->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('title')->sort()->search(),
            TableColumn::make('status')->asBadge(),
            TableColumn::make('created_at')->asDateTime()->sort(),
        ];
    }
}
```

在您的 `AppServiceProvider::boot()` 中注册：

```php
use Dskripchenko\LaravelAdmin\Facades\Admin;
use App\Admin\Resources\ArticleResource;

public function boot(): void
{
    Admin::resources([ArticleResource::class]);
}
```

就这样。列表、创建、编辑、查看等 Screen 会自动生成：

| URL | 内容 |
|---|---|
| `/admin/r/articles` | 列表 + 过滤器 + 分页 |
| `/admin/r/articles/create` | 创建表单 |
| `/admin/r/articles/{id}/edit` | 编辑表单 |
| `/admin/r/articles/{id}` | 只读 infolist |

## 后续步骤

- [将管理面板添加到现有应用](integration.md)——现有用户、路径与域名、代理、
  多个面板、您自己的 laravel-api 模块、升级与故障排查。
- [层级菜单](concepts/menu.md)——用显式的导航树替代自动填充。
- [自定义 Screen](concepts/screens.md)——非 CRUD 页面（表单、报表）。
- [权限](concepts/permissions.md)——按 Action 控制访问。
- [字段参考](fields-reference.md)——完整的字段目录。
- [布局参考](layouts-reference.md)——Tabs/Wizard/Modal/Drawer。
- [演示模式](demo-mode.md)——公开演示站点：一键登录的演示账号与只读保护。

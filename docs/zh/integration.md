---
title: 将管理面板添加到现有 Laravel 应用
audience: developer
status: stable
locale: zh
translated_from: en/integration.md
translated_at: 2026-10-02
---

# 将管理面板添加到现有 Laravel 应用

[快速开始](getting-started.md) 带您从一个全新的应用走到可用的管理面板。本页面向的是
已经拥有用户、API、前置代理和部署流水线的应用：安装器会改动什么、如何让现有用户登录，
以及管理面板如何与其余部分共存。

## 1. 前置条件以及安装器会改动什么

- PHP 8.2+，Laravel 11、12 或 13。
- 一个应用已经在执行迁移的数据库。管理面板把它的状态保存在自己的表中，
  并使用 Laravel 的会话（`web` 中间件组）。
- **不**需要 Node，除非您自行构建前端。

```bash
composer require dskripchenko/laravel-admin
php artisan admin:install            # 单独的 admin_users 表（dedicated）
# 或者
php artisan admin:install --shared   # 由您现有的用户登录（shared）
```

`admin:install` 在每个会改动内容的步骤之前都会询问；每个问题都有对应的参数
（`--no-migrate`、`--no-user`、`--no-composer-hook`、`--force`），
使用 `-n` 时它会以默认值无人值守地运行。

它写入的内容：

| 内容 | 位置 |
|---|---|
| 配置 | `config/admin.php` |
| 迁移 | `database/migrations/2026_01_01_0000*_*.php`（包自身迁移的副本；Laravel 对每个名称只执行一次） |
| 预构建前端 | `public/vendor/admin/`（`assets/` + `source-hash.txt`） |
| Composer 钩子（可选） | 在 `composer.json` 的 `scripts.post-update-cmd` 中追加 `"@php artisan admin:publish --ansi"` |
| 仅 `--shared` | 将 `config/admin.php` 的 `auth` 块指向您的 guard，并添加 `database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php` |
| 仅 `--custom-build` | `resources/js/admin.js`、`vite.config.js` 中 `laravel()` 输入项里的一个条目、`config/admin.php` 中的 `assets.vite_manifest`/`vite_entry` |

`migrate` 创建的表：`admin_users`、`admin_password_resets`、
`admin_roles`、`admin_role_assignments`、`admin_saved_views`、
`admin_dashboard_layouts`、`admin_audit_logs`、`admin_settings`、
`admin_import_processes`。有两个依赖自带迁移，也会一并执行：
`dskripchenko/laravel-delayed-process`（`delayed_processes`）和
`dskripchenko/laravel-translatable`（`languages`、`translations`、
`content_blocks`、`pages`、`page_content_block`）。即使您删除了已发布的副本，
包的迁移仍会从 `vendor/` 加载。

安装器不会改动 `config/auth.php`、您的模型、路由或 `.env`。在 dedicated 策略中，
`admin` guard、`admin_users` provider 和 `admin_users` 密码 broker 是**在运行时**
添加到认证配置中的，并且只在您自己没有定义同名 guard 时才会添加。

### 撤销安装

```bash
# 1. 回滚管理面板的表（对于 --shared，还包括 users 上的列）。
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-admin/database/migrations \
  --path=database/migrations/2026_01_01_000100_add_admin_columns_to_users_table.php
# 依赖的表，如果您自己的其他代码不使用它们：
php artisan migrate:reset \
  --path=vendor/dskripchenko/laravel-delayed-process/databases/migrations \
  --path=vendor/dskripchenko/laravel-translatable/databases/migrations

# 2. 删除文件。
rm config/admin.php database/migrations/2026_01_01_0000*_*admin*.php
rm -r public/vendor/admin
# 以及 composer.json post-update-cmd 中的 "admin:publish" 这一行

# 3. 移除包。
composer remove dskripchenko/laravel-admin
```

`migrate:reset --path` 只回滚在给定路径下找到的迁移，并跳过其余迁移
（"Migration not found"），因此您自己的表会保留。

## 2. 谁来登录：`dedicated` 还是 `shared`

| | `dedicated`（默认） | `shared` |
|---|---|---|
| 管理员存放在 | `admin_users`，模型 `Dskripchenko\LaravelAdmin\Models\AdminUser` | 您的表，例如 `users`，模型 `App\Models\User` |
| guard | `admin`（会话），由包注册 | 您的 guard，例如 `web` |
| 密码重置 | broker `admin_users`，表 `admin_password_resets` | 您的 broker，例如 `users` |
| 谁可以打开管理面板 | `admin_users` 中每一个活跃的行 | 仅限至少拥有一个管理角色的用户（或 `canAccessAdmin()` 放行的用户） |
| 适用于 | 员工并非网站用户的后台 | 每人一个账号：员工本来就登录网站 |

拿不准时选择 `dedicated`：它从不触碰您的用户。本节以下内容都是关于 `shared` 的。

### shared 策略，逐步说明

这套步骤已在一个标准的 Laravel 13 应用上端到端地验证过，该应用的 `users`
表中已有用户：API 登录、`system/me`、主题/locale、2FA 注册与登录、
被拒绝的普通用户，以及浏览器登录。

**1. 使用 `--shared` 安装：**

```bash
php artisan admin:install --shared
```

它会读取您的认证配置——`auth.defaults.guard`、该 guard 的 provider、
provider 的模型以及 `auth.defaults.passwords`——并将其写入 `config/admin.php`：

```php
'auth' => [
    'strategy' => env('ADMIN_AUTH_STRATEGY', 'shared'),
    'guard' => env('ADMIN_GUARD', 'web'),
    'provider' => env('ADMIN_PROVIDER', 'users'),
    'model' => \App\Models\User::class,
    'table' => 'admin_users',        // 保持不变：见下文
    'password_broker' => 'users',
    // ...
],
```

手动完成也是同样的五个值：在 `.env`（或配置）中设置 `ADMIN_AUTH_STRATEGY=shared`、
`ADMIN_GUARD=web`、`ADMIN_PROVIDER=users`，并在 `config/admin.php` 中设置
`model` / `password_broker`（它们没有对应的 env 键）。

请保持 `auth.table` 为 `admin_users`：包自身的 `admin_users`
迁移在两种策略下都会执行，并创建这里所指定名称的表，因此若把它指向 `users`，
该迁移就会失败。在 shared 策略中，这张表只是保持为空。

**2. 为您的 users 表添加管理列。** `--shared` 会发布
`2026_01_01_000100_add_admin_columns_to_users_table.php`（或执行
`php artisan vendor:publish --tag=admin-shared-migrations`），由 `migrate`
应用它。它会向 `admin.auth.model` 对应的表添加列，并跳过任何已存在的列：

| 列 | 用途 |
|---|---|
| `locale` `string(8)`、`theme` `string(16)` | 用户在面板中的选择；没有它们时切换主题或语言会返回 500 |
| `is_active` `boolean default true` | 停用账号；`false` 会拒绝管理面板登录并结束正在进行的管理会话 |
| `last_login_at`、`last_login_ip` | 每次登录管理面板时写入（列不存在时跳过） |
| `two_factor_secret`、`two_factor_recovery_codes`、`two_factor_confirmed_at` | 管理面板的 TOTP 2FA |

回滚会删除这些列。如果您的表之前已有其中一些列，请从迁移的 `down()` 中删掉它们。

**3. 为模型添加两个 trait：**

```php
use Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;

class User extends Authenticatable
{
    use HasAdminAccess, HasAdminTwoFactor, HasFactory, Notifiable;
    // ...
}
```

- `HasAdminAccess`——管理角色（经由 `admin_role_assignments` 的 `roles()`、
  `assignRole()`、`revokeRole()`、`hasAccess()`、`getAllPermissions()`）。
- `HasAdminTwoFactor`——2FA 列的加密 cast 以及
  `hasTwoFactorEnabled()`。没有它，在个人资料中启用了 2FA 的用户仍会在不输入验证码的情况下被放行。

**4. 将某人设为管理员。** 一个现有用户，按邮箱：

```bash
php artisan admin:user alice@example.com --super
```

或者创建一个新用户：`php artisan admin:user "Alice" alice@example.com secret123 --super`。
在代码中：`$user->assignRole('super-admin')`（该角色由第一次执行
`admin:user --super` 时创建）或您自己的任意角色，参见
[第 6 节](#6-权限与角色)。

**5. 登录**：在 `/admin/login` 使用该用户平常的密码登录。通过 API：

```bash
# 先获取会话 cookie 和 XSRF-TOKEN cookie
curl -c jar -b jar -s -o /dev/null http://localhost:8000/admin
XSRF=$(grep XSRF-TOKEN jar | awk '{print $7}' | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

curl -c jar -b jar -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","password":"secret123"}' \
  http://localhost:8000/api/admin/auth/login
# {"success":true,"payload":{"user":{"id":1,...},"permissions":["*"],"redirect_url":"/admin"}}

curl -c jar -b jar -H 'Accept: application/json' http://localhost:8000/api/admin/system/me
# {"success":true,"payload":{"id":1,"name":"Alice",...}}
```

### 谁可以进入

在 shared 策略中，网站用户默认**不是**管理员。对于没有管理角色的用户，登录会返回
`403 {"errorKey":"forbidden"}`（"You do not have access to
the admin panel"），管理 API 对这类用户的网站会话也作同样的响应，shell
则把他们视为访客。用户的网站会话不受影响。

如果想由您自己决定——按邮箱域名、某个列、某个 gate 判断是否为员工——请在模型上定义
`canAccessAdmin()`；它的结果会取代角色检查（在 dedicated 策略中同样适用）：

```php
public function canAccessAdmin(string $panelId): bool
{
    return $this->is_staff;
}
```

模型还可以出于自身原因（公司被暂停、合同已过期）通过
`isDisabledForLogin(): bool` 拒绝登录；值为 `false` 的
`is_active`（或 `enabled`）也有同样效果。

### 一次登录还是两次

使用 `ADMIN_GUARD=web` 时，管理面板与网站共享同一次登录：登录管理面板即登录网站，
退出管理面板也会退出网站。若要在同一张表上保持两套独立的登录，请在
`config/auth.php` 中声明您自己的 guard，并让管理面板指向它：

```php
// config/auth.php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'admin' => ['driver' => 'session', 'provider' => 'users'],
],
```

```dotenv
ADMIN_AUTH_STRATEGY=shared
ADMIN_GUARD=admin
ADMIN_PROVIDER=users
```

会话 cookie 仍是应用的那一个；分开的只是登录状态。

### shared 策略的注意事项

- `spatie/laravel-permission` 的 `HasRoles` 同样定义了 `roles()`、`assignRole()`
  和 `getAllPermissions()`，因此这两个 trait 不能放在同一个类上。
  请把管理面板放在基于同一张表的子类上，并在 `admin.auth.model`
  和 provider 中指定它（如上所述使用单独的 guard）：
  `class AdminAccount extends User { use HasAdminAccess, HasAdminTwoFactor; }`。
  trait 方法会覆盖继承来的方法。
- Laravel Fortify 将其 2FA 保存在相同的 `two_factor_*` 列中，但是手动加密。
  不要给这样的模型添加 `HasAdminTwoFactor`，也不要从管理面板的个人资料中启用 2FA。
- `dskripchenko/laravel-admin-starter` 的 "Users" Resource 编辑的是
  `admin_users`；请将其关闭（在 `config/admin-starter.php` 中设置
  `'resources' => ['users' => false]`），并用您自己的 Resource 管理用户。
- 测试辅助工具 `ActsAsAdmin` 遵循所选策略：在 shared 应用中，它会创建您自己的用户
  （`admin.auth.model`），为其分配角色，并通过您的 guard 登录。

## 3. 路径、域名、API、会话、代理

| 设置 | 默认 | 作用 |
|---|---|---|
| `ADMIN_PATH` | `admin` | SPA 位于 `/{path}/*`；`''` 将其挂载在根路径 |
| `ADMIN_DOMAIN` | 无 | 将 SPA 路由绑定到某个主机，例如 `admin.example.com` |
| `laravel-api.prefix` | `api` | API 位于 `/{prefix}/admin/{controller}/{action}` |
| `ADMIN_API_PATH` | 自动推导 | 提供给 SPA 的 API URL；请不要设置 |

```dotenv
ADMIN_PATH=backoffice     # → /backoffice/login
```

**API 路径。** 管理面板的 API 是 `dskripchenko/laravel-api` 的一个版本，
因此它在 laravel-api 的前缀下提供服务：默认是 `/api/admin/*`，
在 `config/laravel-api.php` 中设置 `'prefix' => 'backend'` 时则为 `/backend/admin/*`。
SPA 从同一个前缀获取 API URL，因此修改前缀会同时移动两者。只有当代理改写了浏览器看到的路径时，
才需要设置 `ADMIN_API_PATH`；它不会移动路由。

**单独的域名。** `ADMIN_DOMAIN` 限制的是 shell 路由；API 路由不绑定域名，
SPA 会在其被加载的主机上调用它们。设置 `ADMIN_DOMAIN=admin.example.com` 和
`ADMIN_PATH=` 时，管理面板就是该主机的根，不会遮蔽主站；API 前缀仍在 shell
的兜底路由之外。

**会话与 cookie。** 管理面板使用应用的会话（`config/session.php`）：`web`
中间件组是 `admin.middleware.shell` 和 `admin.middleware.api` 的第一项。
您为网站设置的一切——驱动、有效期、`SESSION_DOMAIN`、`SESSION_SECURE_COOKIE`、
`SESSION_SAME_SITE`——都适用于管理面板。如果管理面板位于自己的子域名上，
并且需要与网站共享登录，请设置 `SESSION_DOMAIN=.example.com`。

**CSRF。** 每个管理 API 调用都会经过 `web`，包括 CSRF 检查。SPA 会自动从
`XSRF-TOKEN` cookie 中取值并发送 `X-XSRF-TOKEN`。使用会话调用 API
的脚本也必须这样做（参见上面的 `curl` 示例）；不带它的请求会得到 `419`。
机器客户端则改用个人资料中的个人 API 令牌（安装了 Sanctum 时）。

**位于代理或负载均衡器之后。** shell 交给 SPA 的是通过 `url()` 构建的绝对 URL。
如果位于未被信任的 TLS 终止代理之后，这些 URL 会变成 `http://…`，
浏览器会将其作为混合内容拦截。请在 `bootstrap/app.php` 中信任该代理：

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');   // 或代理的地址
})
```

并将 `APP_URL` 设置为公开 URL。

**速率限制。** 登录和 2FA 步骤使用 `ADMIN_LOGIN_THROTTLE`（`5,1`——每分钟五次尝试），
密码重置邮件每五分钟三封，API 的其余部分使用 `ADMIN_API_THROTTLE`（`240,1`）。

## 4. 多个面板

一个面板——`admin`——就能满足大多数应用。当第二类受众需要自己的界面时，再添加面板：
例如在后台旁边的客户中心，拥有自己的用户、登录页、菜单和 Resource。

每个面板都有自己的挂载路径、guard 和用户模型，自己的 API 版本
（`/api/{id}/*`）、中间件和插件。默认面板由 `config/admin.php`
的顶层键构建；其他面板列在 `admin.panels` 中：

```php
// config/admin.php
'panels' => [
    'client' => [
        'path' => 'cabinet',                         // SPA 位于 /cabinet
        'auth' => [
            'strategy' => 'dedicated',               // guard/provider/broker 会自动为您注册
            'guard' => 'client',
            'provider' => 'client_users',
            'model' => App\Models\ClientUser::class, // HasAdminAccess，一个 `enabled` 或 `is_active` 列
            'table' => 'client_users',
            'password_broker' => 'client_users',
        ],
        'api' => App\Admin\ClientApi::class,         // API 位于 /api/client/*
        'plugins' => [App\Admin\ClientPanelPlugin::class],
    ],
],
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Panel\PanelApi;

final class ClientApi extends PanelApi {}   // 每个面板一个子类
```

```php
namespace App\Admin;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Menu\MenuNode;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

final class ClientPanelPlugin implements AdminPlugin
{
    public function name(): string { return 'client-panel'; }
    public function version(): string { return '1.0.0'; }
    public function register(): void {}

    public function boot(Admin $admin): void
    {
        // 在这里注册的 Resource 只属于 `client` 面板。
        $admin->resources([ProjectResource::class]);
        $admin->menu()->add(MenuNode::resource('projects'));
    }
}
```

`client_users` 表和模型需要您自己创建。挂载在根路径（`'path' => ''`）的面板必须列出
它不应吞掉的前缀：`'exclude_prefixes' => ['api', 'admin']`。面板的
`middleware.api` 条目会被添加到共享的 `admin.middleware.api` 栈中。

## 5. 您自己的 `dskripchenko/laravel-api` 模块

laravel-api 通过一个 `api_module` 绑定解析所有 API 版本。管理面板将其绑定到
`Dskripchenko\LaravelAdmin\Http\AdminApiModule`，其版本为 `admin` 以及各个面板。
如果您的应用绑定了自己的模块——通常是带有 `getApiModule()` 的
`ApiServiceProvider extends
Dskripchenko\LaravelApi\Providers\ApiServiceProvider`——您的模块注册得更晚并会胜出，
而继承 laravel-api 的 `BaseModule` 的模块会丢掉管理 API：`/api/admin/*` 变成 404。

请改为继承 `AdminApiModule`，并将您的版本添加到父类的列表中：

```php
namespace App\Api;

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;

class ApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return array_merge(parent::getApiVersionList(), [
            'v1' => V1Api::class,          // 您的 BaseApi
        ]);
    }
}
```

```php
namespace App\Providers;

use App\Api\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseApiServiceProvider;

class ApiServiceProvider extends BaseApiServiceProvider
{
    protected function getApiModule()
    {
        return new ApiModule;
    }
}
```

这样 `/api/v1/*` 和 `/api/admin/*` 就会并行提供服务。

- 不要覆盖 `getApiMiddleware()`。自 1.32.0 起，模块的中间件组与版本无关，
  中间件栈按请求选择：管理面板及各面板获得 `admin.middleware.api`（会话、CSRF、`AdminAuth`），
  您的版本只获得其自身 `BaseApi::getMethods()` 声明的中间件。
  因此，使用 bearer 令牌的无状态 `v1` 不会从管理面板获得会话和 CSRF；
  如果某个版本需要会话或 `AdminLocale`，应由它自己声明。
- `getApiPrefix()` 和 `getApiUriPattern()` 读取 `laravel-api.prefix` 和
  `laravel-api.uri_pattern`，所有版本共享这两项。修改前缀也会移动管理 API，
  SPA 会随之调整（参见[第 3 节](#3-路径域名api会话代理)）。
- 不要使用与面板 id 相同的版本 id（`admin`，或 `admin.panels` 的某个键）。

## 6. 权限与角色

权限是字符串，`admin.{domain}.{action}`，支持通配符
（`admin.articles.*`、`*`）。角色（`admin_roles`）持有权限列表，并通过
`admin_role_assignments` 分配给用户；一个用户可以拥有多个角色。
每个 Resource 都会获得 `admin.{slug}.view/create/update/delete`（启用时还有 restore、
replicate 等）；Screen 和设置也以同样方式控制访问。参见[权限](concepts/permissions.md)。

```bash
php artisan admin:user --super                 # 交互式；Super Admin = ['*']
php artisan admin:user alice@example.com --super   # 授予现有用户
```

```php
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

$editor = Role::create([
    'name' => '编辑',
    'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($editor);        // 或 ->assignRole('editor')
$user->hasAccess('admin.articles.update');   // true
$user->revokeRole('editor');
```

角色管理界面由 starter 包提供：

```bash
composer require dskripchenko/laravel-admin-starter
```

它会自行注册，并添加 Resource："Users"（`admin_users`）、"Roles"
（从所有已注册 Resource 和插件的权限中选取）以及只读的 "Audit log"。
在 shared 策略中请关闭 "Users" Resource，见上文。

### 双因素认证

每个用户都可以在个人资料中开启 TOTP 双因素认证。`config/admin.php`
中有两项设置可以改变这一点：

```php
'auth' => [
    'two_factor' => [
        // false：个人资料中不提供 2FA 设置，启用请求会被拒绝
        // （403 two_factor_disabled）。之前已注册的用户在登录时仍需通过
        // 验证，并且仍可以将其关闭。
        'enabled' => true,
        // 角色 slug，其持有者必须先启用 2FA；'*' 表示所有人。
        'enforce_for' => ['super-admin', 'security'],
    ],
],
```

受 `enforce_for` 约束但尚未设置 2FA 的用户只能访问个人资料、会话和 shell：
任何其他 API 请求都会以 403 和 `errorKey: two_factor_setup_required` 响应，
SPA 会把他们留在个人资料的 "Security" 部分，直到开启 2FA。

## 7. 前端

**预构建（默认）。** 包中自带已构建的 SPA；`admin:install` 将其复制到
`public/vendor/admin`，由 shell 加载。每次包更新后都必须刷新这份副本——
这正是 composer 钩子所做的事：

```json
"post-update-cmd": [
    "@php artisan admin:publish --ansi"
]
```

如果您跳过了这个钩子，请添加它，或者在部署中于 `composer install` 之后执行
`php artisan admin:publish`。`public/vendor/admin` 可以像其他公共资源一样提交到仓库，
或在 CI 中构建。

**您自己的构建**——用于以 Vue 编写的自定义字段、小部件、布局或页面：

```bash
php artisan admin:install --custom-build
npm i -D @dskripchenko/laravel-admin @dskripchenko/ui @dskripchenko/wysiwyg
npm run build
```

此后 shell 会加载您的 Vite 构建（`config/admin.php` 中的 `assets.vite_manifest` /
`assets.vite_entry`），而不是预构建副本。请让 npm 包与 composer 包保持同一版本。
参见[前端扩展](frontend-extension.md)。

## 8. 升级与故障排查

### 升级

```bash
composer update dskripchenko/laravel-admin
php artisan migrate                  # 新迁移从 vendor/ 加载
php artisan admin:publish            # composer 钩子会替您完成
php artisan config:clear             # 如果您缓存了配置
```

请先阅读 [CHANGELOG](../../CHANGELOG.md)。包的配置以一层深度合并到您的配置之下：
`config/admin.php` 中新增的顶层键会采用包的默认值，但在您已发布的某个块内部
（比如 `auth` 内部）新增的键，在您将其复制过来之前并不存在。
当某个版本提到配置变更时，请将您的文件与
`vendor/dskripchenko/laravel-admin/config/admin.php` 进行比较。如果使用您自己的构建，
请将 npm 包更新到相同版本并重新构建。

### 故障排查

**空白页面或 "The admin frontend was not found"。** shell 没有找到前端：
执行 `php artisan admin:publish`（预构建）或 `npm run build`
（自己的构建；检查 `assets.vite_manifest` 是否指向一个存在的 manifest）。
`/vendor/admin/assets/*` 返回 404 意味着 `public/vendor/admin` 没有到达服务器——
部署没有包含 `public/vendor`，或者 Web 服务器的文档根目录不是 `public/`。

**出现提示条 "The admin files are out of date: run php artisan admin:publish"。**
已发布的副本与已安装包中的副本不同——包更新后没有重新发布。执行 `admin:publish`，
并添加 composer 钩子，避免再次发生。

**登录成功后立即出现 401。** 会话 cookie 没有被带回：
- 管理面板通过普通 HTTP 提供服务，却设置了 `SESSION_SECURE_COOKIE=true`；
- `SESSION_DOMAIN` 与打开管理面板的主机不匹配（或者管理面板位于子域名上，
  而该域名没有共享）；
- 使用了 `array` 会话驱动，或者缓存/会话存储没有在服务器之间共享；
- 代理未被信任，因此请求看起来像 HTTP（参见
  [第 3 节](#3-路径域名api会话代理)）。
`401 {"errorKey":"session_expired"}` 表示用户的密码在登录后被修改过：请重新登录。

**403 "You do not have access to the admin panel"。** shared 策略下用户没有管理角色——
为其授予一个（`admin:user email --super`）。
`403 account_inactive` 表示账号已停用（`is_active` 或 `enabled` 为
false，或 `isDisabledForLogin()` 作出了这样的判断）。

**脚本调用 API 时出现 419。** 请求没有 CSRF 令牌：从 cookie 中取值发送
`X-XSRF-TOKEN`，或者使用 API 令牌。

**语言不对。** 管理面板的 locale 按以下顺序选择：`?locale=`、
SPA 的 `X-Admin-Locale` 请求头、用户的 `locale` 列、`admin_locale` cookie、
浏览器的 `Accept-Language`，最后是 `ADMIN_LOCALE` / `admin.ui.default_locale`
或 `config('app.locale')`。只有 `admin.ui.available_locales`
（默认为 `['ru', 'en']`）中的语言才会被考虑。您自己字符串的翻译放在
`lang/{locale}.json` 中。

**`/api/admin/*` 返回 404。** 您的应用绑定了自己的 laravel-api 模块，且该模块没有继承
`AdminApiModule`——参见[第 5 节](#5-您自己的-dskripchenkolaravel-api-模块)。
或者路由缓存于安装之前：`php artisan route:clear`。

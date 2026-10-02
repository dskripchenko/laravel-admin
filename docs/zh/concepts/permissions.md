---
title: 权限
audience: developer
status: stable
locale: zh
translated_from: en/concepts/permissions.md
translated_at: 2026-10-02
---

# 权限

RBAC：用户持有角色，角色持有权限字符串，每个操作都由 `AdminAccess` 中间件把关。

## 权限键

格式：`admin.{domain}.{action}`。示例：

- `admin.users.view`
- `admin.articles.update`
- `admin.system.settings.edit`
- `admin.audit.view`

通配符：
- `admin.users.*`——users 域中的所有操作
- `admin.*.view`——对所有内容的查看权限
- `admin.*`——所有管理权限
- `*`——超级管理员

带 `*` 的已授予键使用 `fnmatch()` 匹配，其中 `*` 也匹配点号：`admin.*` 同样覆盖
`admin.cms.articles.view`。

## 角色

存储在 `admin_roles` 中；`permissions` 是一个字符串的 JSON 列表。使用
`HasAdminAccess` trait 的用户模型通过 `assignRole()`（传入 `Role`、其 id 或其
slug）获得角色，通过 `revokeRole()` 移除角色：

```php
$role = Role::create([
    'name' => 'Editor', 'slug' => 'editor',
    'permissions' => ['admin.articles.*', 'admin.media.view'],
]);
$user->assignRole($role);
```

## Resource 自动权限

对于每个 Resource，管理面板会从 `Resource::permission()`（默认为
`admin.{slug}`）派生权限键，并用它们保护该 Resource 的路由：

```
admin.articles.view
admin.articles.create
admin.articles.update
admin.articles.delete
admin.articles.restore         (if soft-delete)
admin.articles.force-delete    (if soft-delete)
admin.articles.replicate       (if replicable)
admin.articles.reorder         (if reorderable)
```

`AdminAccess:admin.articles.create` 中间件会自动保护 `create` 路由。自定义
Action（`POST /{slug}/action`）需要 `.view`。这些键仅用于权限检查：若要让它们
在角色编辑器中可勾选，请通过 `Admin::permissions()` 注册（见下文）。

通过 `Resource::permission()` 覆盖基础前缀：

```php
public static function permission(): string
{
    return 'admin.cms.articles';   // → admin.cms.articles.view, .update, ...
}
```

## 注册自定义权限

```php
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;

Admin::permissions(
    ItemPermission::group('Reports')
        ->addPermission('admin.reports.view', 'View reports')
        ->addPermission('admin.reports.export', 'Export reports'),
);
```

它们会作为可勾选项出现在角色编辑页面中。

请以源字符串（或翻译键）的形式传入分组名和标签，而不是 `__()` 的结果：分组在
启动时只注册一次，标签会在下发时按该请求的 locale 经 JSON 翻译进行翻译。已经
翻译过的标签会原样返回。

## 在代码中检查

```php
$user->hasAccess('admin.articles.update');   // true / false
$user->hasAnyAccess(['admin.articles.update', 'admin.articles.delete']);  // OR
$user->hasAllAccess(['admin.articles.update', 'admin.articles.delete']);  // AND
```

在 SPA 中（bootstrap 和登录响应携带用户的扁平权限列表；store 匹配相同的 `*`
掩码）：

```ts
const auth = useAuthStore()
auth.hasAnyPermission(['admin.articles.update'])
```

## 操作上的中间件

`AdminAccess` 中间件接受一个或多个以 `;` 分隔的权限：

```php
'middleware' => [AdminAccess::class.':admin.users.view'],
'middleware' => [AdminAccess::class.':admin.users.view;admin.users.update'],  // AND
```

若需要多个权限之间的 OR 语义——请在操作处理器内部显式检查。

## Screen

Screen 通过 `permission()` 声明其权限检查：

```php
public function permission(): array|string|null
{
    return ['admin.reports.view', 'admin.exports.run'];   // AND
}
```

`state`（GET）、`runMethod` 和 `listener`（POST）端点会被自动保护。`null`
（默认值）表示仅需通过身份认证即可。

## 设置（Settings）

每个 `SettingsResource` 获得 `admin.settings.{slug}.view` /
`admin.settings.{slug}.update`。

## 2FA

独立于 RBAC。如果为某个用户启用了 2FA，无论其角色如何，登录流程都要求输入 TOTP
验证码。

## 另请参阅

- [Resource](resources.md)——每个 CRUD 操作的自动权限
- [Screen](screens.md)——`permission()` 方法
- [多租户](tenancy.md)——租户范围的数据与权限

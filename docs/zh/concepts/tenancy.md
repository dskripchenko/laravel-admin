---
title: 多租户
audience: developer
status: stable
locale: zh
translated_from: en/concepts/tenancy.md
translated_at: 2026-10-02
---

# 多租户

管理面板并不选择多租户策略——那由宿主决定
（基于域名、基于请求头、基于路径、中心数据库还是每租户一个数据库）。
我们提供契约和一个作用域 trait。

## 契约

| 类 | 角色 |
|---|---|
| `Tenancy\Tenant` | 你的租户实体的接口：`getTenantKey()` 和 `getTenantLabel()`。 |
| `Tenancy\TenantResolver` | 知道当前租户：`current()`、`setCurrent()`、`available($user)`。 |
| `Tenancy\TenantContext` | 面向作用域和 trait 的 resolver 外观：`current()`、`currentKey()`、`withTenant()`。 |
| `Tenancy\SingleTenantResolver` | 单租户部署的默认实现：除非显式设置，否则 `current()` 为 null。 |
| `Tenancy\Concerns\TenantScoped`（trait） | 自动把 Eloquent 模型限定到当前租户。 |
| `Tenancy\TenantedModel` | 已应用 `TenantScoped` 的抽象模型。 |

`TenantResolver` 和 `TenantContext` 以 `scoped()`（每请求）方式绑定，
因此在长时间运行的运行时（Octane / 队列 worker）中，state 不会在
请求之间泄漏。

## 租户实现

```php
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;

class Organization extends Model implements Tenant
{
    public function getTenantKey(): int|string
    {
        return $this->id;
    }

    public function getTenantLabel(): string
    {
        return $this->name;
    }
}
```

## Resolver 实现

resolver 自己根据请求携带的任何信息找到租户——
我们没有任何中间件替它填充：

```php
namespace App\Tenancy;

use App\Models\Organization;
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;
use Dskripchenko\LaravelAdmin\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Model;

final class HeaderTenantResolver implements TenantResolver
{
    private ?Tenant $current = null;

    private bool $resolved = false;

    public function current(): ?Tenant
    {
        if (! $this->resolved) {
            $id = request()->header('X-Tenant-Id');
            $this->current = $id ? Organization::find($id) : null;
            $this->resolved = true;
        }

        return $this->current;
    }

    public function setCurrent(?Tenant $tenant): void
    {
        $this->current = $tenant;
        $this->resolved = true;
    }

    public function available(?Model $user = null): array
    {
        return $user ? $user->organizations()->get()->all() : [];
    }
}
```

在你的 service provider 中绑定：

```php
$this->app->scoped(TenantResolver::class, HeaderTenantResolver::class);
```

`setCurrent()` 用于控制台命令、cron 任务和测试；要以另一个租户的身份
运行一段代码并在之后恢复先前的租户：

```php
app(TenantContext::class)->withTenant($organization, fn () => Report::build());
```

## TenantScoped trait

```php
use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;

class Article extends Model
{
    use TenantScoped;

    // protected static string $tenantColumn = 'organization_id';
}
```

行为：

- 全局作用域（`TenantScope`）对所有查询应用
  `where('{table}.tenant_id', $tenant->getTenantKey())`。
- `creating` 事件在 `tenant_id` 为空时填充它。
- 列名为 `tenant_id`，除非模型声明了静态属性
  `$tenantColumn`。
- 要绕过该作用域（超级管理员视图、跨租户报表），请使用
  Eloquent 自带的 `Article::withoutGlobalScope(TenantScope::class)`。

## 按租户的权限

权限默认是全局的。对于限定到租户的权限，可以组合键名
（`admin.{tenant_id}.articles.view`），或者把
`$user->hasAccess('...')` 与你自己的租户成员资格检查结合使用——
本包自身不提供感知租户的权限检查。

## 按租户的设置

`SettingsResource` 不会自动限定作用域：默认存储在 `admin_settings` 中
为每个设置 slug 保存一组行。若需按租户配置，请在该 Resource 上重写
`read(SettingsStorage $storage)` / `write(SettingsStorage $storage, array $values)`，
或者绑定你自己的 `Settings\Storage\SettingsStorage`。

## 前端感知

bootstrap 载荷不携带租户信息。`AdminSidebar` 接受一个可选的
`tenant` prop，传入时会在品牌标识下方显示租户区块——
在自定义 shell 构建中很有用：

```vue
<AdminSidebar :tenant="{ label: '工作区', name: tenant.name }" />
```

## 说明

- 多租户是**可选启用**的——默认的 `SingleTenantResolver` 返回
  null，`TenantScoped` 变为空操作（既不过滤也不填充）。
- 对于完整的策略（每租户一个数据库、每租户一个 schema），请使用
  专门的包（例如 `stancl/tenancy` 或 `dskripchenko/laravel-schemify`），
  并让你的 `TenantResolver` 返回它解析出的租户。

## 另请参阅

- [权限](permissions.md)
- [架构](../architecture.md)
- [多租户实战](../../ru/recipes/multi-tenancy.md)（俄文）

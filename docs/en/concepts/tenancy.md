---
title: Tenancy
audience: developer
status: stable
locale: en
---

# Tenancy

The admin doesn't pick a tenancy strategy — that's the host's call
(domain-based, header-based, path-based, central-DB-vs-per-tenant-DB).
We provide contracts and a scoped trait.

## Contracts

| Class | Role |
|---|---|
| `Tenancy\Tenant` | Interface of your tenant entity: `getTenantKey()` and `getTenantLabel()`. |
| `Tenancy\TenantResolver` | Knows the current tenant: `current()`, `setCurrent()`, `available($user)`. |
| `Tenancy\TenantContext` | Facade over the resolver for scopes and traits: `current()`, `currentKey()`, `withTenant()`. |
| `Tenancy\SingleTenantResolver` | Default for single-tenant deployments: `current()` is null unless set. |
| `Tenancy\Concerns\TenantScoped` (trait) | Auto-scope an Eloquent model to the current tenant. |
| `Tenancy\TenantedModel` | Abstract model with `TenantScoped` already applied. |

`TenantResolver` and `TenantContext` are bound as `scoped()` (per-request),
so in long-running runtimes (Octane / queue workers) state doesn't leak
between requests.

## Tenant implementation

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

## Resolver implementation

The resolver finds the tenant itself, from whatever the request carries —
there is no middleware of ours that fills it in:

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

Bind in your service provider:

```php
$this->app->scoped(TenantResolver::class, HeaderTenantResolver::class);
```

`setCurrent()` is for console commands, cron jobs and tests; to run a piece
of code as another tenant and restore the previous one afterwards:

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

Behaviour:

- A global scope (`TenantScope`) applies
  `where('{table}.tenant_id', $tenant->getTenantKey())` to all queries.
- `creating` event fills `tenant_id` when it is empty.
- The column is `tenant_id` unless the model declares a static
  `$tenantColumn`.
- To escape the scope (admin-superuser views, cross-tenant reports), use
  Eloquent's own `Article::withoutGlobalScope(TenantScope::class)`.

## Per-tenant permissions

Permissions are global by default. For tenant-scoped ones, either compose
the key (`admin.{tenant_id}.articles.view`) or check
`$user->hasAccess('...')` together with your own tenant-membership check —
the package has no tenant-aware permission check of its own.

## Per-tenant settings

`SettingsResource` doesn't auto-scope: the default storage keeps one row
set per settings slug in `admin_settings`. For per-tenant config, override
`read(SettingsStorage $storage)` / `write(SettingsStorage $storage, array $values)`
on the resource, or bind your own `Settings\Storage\SettingsStorage`.

## Frontend awareness

The bootstrap does not carry the tenant. `AdminSidebar` accepts an
optional `tenant` prop and shows a tenant block below the brand when it is
given — useful in a custom shell build:

```vue
<AdminSidebar :tenant="{ label: 'Workspace', name: tenant.name }" />
```

## Notes

- Tenancy is **opt-in** — the default `SingleTenantResolver` returns
  null and `TenantScoped` becomes a no-op (neither filtering nor filling).
- For complete strategies (DB-per-tenant, schema-per-tenant), use a
  dedicated package (e.g. `stancl/tenancy` or `dskripchenko/laravel-schemify`)
  and make your `TenantResolver` return the tenant it resolved.

## See also

- [Permissions](permissions.md)
- [Architecture](../architecture.md)
- [Multi-tenancy recipe](../../ru/recipes/multi-tenancy.md) (ru)

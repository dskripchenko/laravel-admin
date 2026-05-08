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
| `Tenancy\Tenant` | Marker interface for your tenant entity. |
| `Tenancy\TenantResolver` | Resolves the current tenant from the request. |
| `Tenancy\TenantContext` | Per-request scope: get/set the active tenant. |
| `Tenancy\SingleTenantResolver` | Default no-op for single-tenant deployments. |
| `Tenancy\TenantScoped` (trait) | Auto-scope an Eloquent model to the current tenant. |

`TenantContext` is bound as `scoped()` (per-request), so in long-running
runtimes (Octane / queue workers) state doesn't leak between requests.

## Resolver implementation

```php
namespace App\Tenancy;

use Dskripchenko\LaravelAdmin\Tenancy\Tenant;
use Dskripchenko\LaravelAdmin\Tenancy\TenantResolver;
use Illuminate\Http\Request;

final class HeaderTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $id = $request->header('X-Tenant-Id');
        return $id ? \App\Models\Tenant::find($id) : null;
    }
}
```

Bind in your service provider:

```php
$this->app->scoped(TenantResolver::class, HeaderTenantResolver::class);
```

`TenantContext` is populated by middleware on every admin request
(install in `config('admin.middleware.api')`).

## TenantScoped trait

```php
use Dskripchenko\LaravelAdmin\Tenancy\TenantScoped;

class Article extends Model
{
    use TenantScoped;
}
```

Behaviour:

- A global scope applies `where('tenant_id', $current->getKey())` to all
  queries.
- `creating` event auto-fills `tenant_id`.
- `withoutTenancy()` query macro escapes the scope (admin-superuser
  views, cross-tenant reports).

## Per-tenant permissions

Permissions are global by default. For tenant-scoped:

- Either compose them: `admin.{tenant_id}.articles.view`.
- Or check `$user->hasAccess('...')` AND
  `$user->hasTenantAccess($tenant)` separately.

## Per-tenant settings

`SettingsResource` doesn't auto-scope. Add `tenant_id` to your
`admin_settings` rows and override `read()`/`write()` if you need
per-tenant config.

## Frontend awareness

The bootstrap response includes `tenant` (if set):

```ts
interface AdminBootstrap {
  tenant: { id: string|number, name: string } | null
  // ...
}
```

`AdminSidebar` displays the tenant block if provided:

```vue
<AdminSidebar :tenant="{ label: 'Workspace', name: tenant.name }" />
```

## Notes

- Tenancy is **opt-in** — the default `SingleTenantResolver` returns
  null and `TenantScoped` becomes a no-op.
- For complete strategies (DB-per-tenant, schema-per-tenant), use a
  dedicated package (e.g. `stancl/tenancy`) — its events fire before
  our middleware, our `TenantContext` picks up the resolved tenant.

## See also

- [Permissions](permissions.md)
- [Architecture](../architecture.md)

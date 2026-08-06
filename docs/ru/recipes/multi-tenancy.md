# Multi-tenancy

Laravel-admin даёт **контракты**, а не реализацию. Решения о том, как именно
разрешать tenant'а (subdomain, header, slug в URL, привязка к user'у)
принимает host-проект.

## 1. Реализация Tenant интерфейса

```php
// app/Models/Organization.php
class Organization extends Model implements \Dskripchenko\LaravelAdmin\Tenancy\Tenant
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

## 2. Custom TenantResolver

```php
namespace App\Tenancy;

use App\Models\Organization;
use Dskripchenko\LaravelAdmin\Tenancy\{Tenant, TenantResolver};

class SubdomainTenantResolver implements TenantResolver
{
    private ?Tenant $cached = null;

    public function current(): ?Tenant
    {
        if ($this->cached) return $this->cached;

        $host = request()->getHost();                          // 'acme.example.com'
        $subdomain = explode('.', $host)[0];

        return $this->cached = Organization::where('slug', $subdomain)->first();
    }

    public function setCurrent(?Tenant $tenant): void { $this->cached = $tenant; }

    public function available(?\Illuminate\Database\Eloquent\Model $user = null): array
    {
        if (! $user) return [];
        return $user->organizations()->get()->all();           // через relation
    }
}
```

## 3. Override в DI

```php
// AppServiceProvider::register()
$this->app->singleton(
    \Dskripchenko\LaravelAdmin\Tenancy\TenantResolver::class,
    \App\Tenancy\SubdomainTenantResolver::class,
);
```

## 4. Подключение TenantScoped к моделям

```php
class Project extends Model
{
    use \Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;

    // optional: переопределить колонку (default 'tenant_id')
    public static $tenantColumn = 'organization_id';
}
```

С этого момента:

- Все запросы `Project::query()->...` автоматически `WHERE organization_id = current_tenant->id`.
- При `Project::create([...])` без явного `organization_id` — заполнится current'ом.
- В CLI/тестах: `app(TenantContext::class)->withTenant($org, fn () => ...)`.

## 5. Фильтр в Resource (опционально)

Если позволяете суперюзеру переключать tenant'а из admin'ки:

```php
public function filters(): array
{
    return [
        SelectFromModelFilter::for('organization_id')
            ->fromModel(Organization::class, 'name')
            ->label('Организация'),
    ];
}
```

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * The default resolver: the single-tenant mode, which does nothing.
 *
 * `current()` returns only what `setCurrent()` put there, and null by default.
 * It is used when multi-tenancy is not needed: no tenant switcher appears in
 * the admin, and the TenantScoped trait filters nothing.
 */
final class SingleTenantResolver implements TenantResolver
{
    private ?Tenant $current = null;

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function setCurrent(?Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    /**
     * @return list<Tenant>
     */
    public function available(?Model $user = null): array
    {
        return $this->current === null ? [] : [$this->current];
    }
}

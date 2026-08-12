<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * The contract of whatever resolves the current tenant of a request.
 *
 * The implementations:
 *   - SingleTenantResolver, the default — the single-tenant mode, where
 *     current() is null.
 *   - SubdomainTenantResolver, HeaderTenantResolver, UserTenantResolver —
 *     the host project's own, resolving the tenant from the request context.
 */
interface TenantResolver
{
    /**
     * The current tenant of the active request; null means the single-tenant mode.
     */
    public function current(): ?Tenant;

    /**
     * Replaces the current tenant by hand — useful in console commands, cron
     * jobs and unit tests.
     */
    public function setCurrent(?Tenant $tenant): void;

    /**
     * Every tenant the user may reach, for the SPA's tenant switcher.
     *
     * @return list<Tenant>
     */
    public function available(?Model $user = null): array;
}

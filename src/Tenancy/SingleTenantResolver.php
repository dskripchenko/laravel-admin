<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Default-резолвер: single-tenant mode (no-op).
 *
 * `current()` возвращает только то, что выставлено через `setCurrent()`
 * (по дефолту null). Используется когда multi-tenancy не нужен — в admin'е
 * не появится tenant-switcher, а TenantScoped trait не будет фильтровать
 * запросы.
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

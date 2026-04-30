<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Контракт резолвера текущего tenant'а на запрос.
 *
 * Реализации:
 *   - SingleTenantResolver (default) — single-tenant mode, current()=null.
 *   - SubdomainTenantResolver / HeaderTenantResolver / UserTenantResolver —
 *     host-проектные, разрешают тenant из request context'а.
 */
interface TenantResolver
{
    /**
     * Текущий tenant для активного request'а. null = single-tenant mode.
     */
    public function current(): ?Tenant;

    /**
     * Заменить текущий tenant вручную. Полезно для CLI-команд / cron'ов /
     * unit-тестов.
     */
    public function setCurrent(?Tenant $tenant): void;

    /**
     * Список всех tenants, к которым у пользователя есть доступ
     * (для tenant-switcher'а в SPA).
     *
     * @return list<Tenant>
     */
    public function available(?Model $user = null): array;
}

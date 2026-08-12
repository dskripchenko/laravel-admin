<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

/**
 * The contract of a tenant — an organization, a company, a project.
 *
 * Implementing it is the host project's business. The minimum is:
 *   - a stable key (string|int) fit for a where(...)
 *   - a human-readable label for the UI.
 *
 * It can be an Eloquent model or a value object.
 */
interface Tenant
{
    /**
     * The stable identifier, for queries and Eloquent relations.
     */
    public function getTenantKey(): int|string;

    /**
     * The name shown in the UI: the header, the dropdown, the badges.
     */
    public function getTenantLabel(): string;
}

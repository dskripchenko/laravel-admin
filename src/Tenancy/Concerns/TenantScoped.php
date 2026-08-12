<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy\Concerns;

use Dskripchenko\LaravelAdmin\Tenancy\TenantContext;
use Dskripchenko\LaravelAdmin\Tenancy\TenantScope;

/**
 * The trait of a tenant-scoped model.
 *
 * On boot it:
 *   - adds the `TenantScope` global scope, filtering every query by
 *     `tenant_id = current_tenant->getTenantKey()`;
 *   - hooks the creating event, so that new records get the current tenant's
 *     `tenant_id` by themselves.
 *
 * The column's name can be overridden with the static `$tenantColumn`
 * property; it is 'tenant_id' by default. With no current tenant — the
 * single-tenant mode — the scope does nothing at all: neither filtering nor
 * filling in.
 */
trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function ($model): void {
            $column = method_exists($model, 'getTenantColumn')
                ? $model->getTenantColumn()
                : 'tenant_id';
            if ($model->getAttribute($column) !== null) {
                return;
            }

            /** @var TenantContext $context */
            $context = app(TenantContext::class);
            $key = $context->currentKey();
            if ($key !== null) {
                $model->setAttribute($column, $key);
            }
        });
    }

    /**
     * The name of the tenant foreign-key column, overridable with a static
     * `$tenantColumn` on the model; `tenant_id` by default.
     */
    public function getTenantColumn(): string
    {
        if (! property_exists(static::class, 'tenantColumn')) {
            return 'tenant_id';
        }

        /** @var array<string, mixed> $vars */
        $vars = get_class_vars(static::class);

        return isset($vars['tenantColumn']) && is_string($vars['tenantColumn'])
            ? $vars['tenantColumn']
            : 'tenant_id';
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The global scope of the TenantScoped models: it filters the queries by
 * `tenant_id = current_tenant->getTenantKey()`.
 *
 * With no current tenant the scope does nothing — the single-tenant mode.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        $key = $context->currentKey();
        if ($key === null) {
            return;
        }

        $column = method_exists($model, 'getTenantColumn')
            ? $model->getTenantColumn()
            : 'tenant_id';

        $builder->where($model->getTable().'.'.$column, '=', $key);
    }
}

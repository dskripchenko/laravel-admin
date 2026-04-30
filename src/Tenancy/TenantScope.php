<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope для TenantScoped-моделей: фильтрует запросы по
 * `tenant_id = current_tenant->getTenantKey()`.
 *
 * Если current tenant = null — scope no-op'ит (single-tenant mode).
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

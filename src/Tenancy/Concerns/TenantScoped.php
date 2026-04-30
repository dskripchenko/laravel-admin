<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy\Concerns;

use Dskripchenko\LaravelAdmin\Tenancy\TenantContext;
use Dskripchenko\LaravelAdmin\Tenancy\TenantScope;

/**
 * Trait для моделей с tenant-scope'ом.
 *
 * При boot:
 *   - добавляет global scope `TenantScope`, который фильтрует все запросы
 *     по `tenant_id = current_tenant->getTenantKey()`;
 *   - hook'ает creating event: автоматически проставляет `tenant_id`
 *     текущего tenant'а в новые записи.
 *
 * Имя колонки можно переопределить статическим property `$tenantColumn`
 * (default 'tenant_id'). Если current tenant = null (single-tenant mode),
 * scope не делает ничего — ни фильтрации, ни авто-установки.
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
     * Имя колонки-tenant-fk. Override через статический `$tenantColumn`
     * на конкретной модели (default `tenant_id`).
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

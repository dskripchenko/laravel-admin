<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

/**
 * Удобный фасад поверх TenantResolver — упрощает доступ из traits/scope'ов.
 *
 * Используется в TenantScoped: в boot()-loop'ах модели нет request-context'а
 * по типу middleware, и нам нужен глобальный доступ к текущему tenant'у.
 */
final class TenantContext
{
    public function __construct(private readonly TenantResolver $resolver) {}

    public function current(): ?Tenant
    {
        return $this->resolver->current();
    }

    public function currentKey(): int|string|null
    {
        return $this->resolver->current()?->getTenantKey();
    }

    /**
     * Запустить callback в контексте определённого tenant'а, восстановить
     * предыдущий после.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withTenant(?Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->resolver->current();
        $this->resolver->setCurrent($tenant);
        try {
            return $callback();
        } finally {
            $this->resolver->setCurrent($previous);
        }
    }
}

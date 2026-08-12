<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

/**
 * A convenient facade over TenantResolver, for reaching it from traits and
 * scopes.
 *
 * TenantScoped uses it: inside a model's boot() loops there is no
 * request context of the middleware kind, and the current tenant has to be
 * reachable globally.
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
     * Runs a callback in a particular tenant's context and restores the
     * previous one afterwards.
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

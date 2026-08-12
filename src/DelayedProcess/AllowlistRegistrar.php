<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\DelayedProcess;

use InvalidArgumentException;

/**
 * The whitelist of the {entity::method} pairs the async-action API may run.
 *
 * Without it the SPA could instantiate any class at all, which is a security
 * risk. We register the permitted handlers explicitly and check them when an
 * async action starts.
 *
 * The registration goes through `Admin::allowAsync($entity, $method)` or from
 * a plugin's AdminPlugin::boot.
 */
final class AllowlistRegistrar
{
    /** @var array<string, list<string>> entity FQCN => list of method names */
    private array $allowed = [];

    /**
     * @param  class-string  $entity
     */
    public function allow(string $entity, string $method): void
    {
        if (! class_exists($entity)) {
            throw new InvalidArgumentException("Allowed async entity `{$entity}` does not exist");
        }

        $existing = $this->allowed[$entity] ?? [];
        if (! in_array($method, $existing, true)) {
            $existing[] = $method;
        }
        $this->allowed[$entity] = $existing;

        // Kept in sync with the delayed-process config, which validates
        // against its own allowed_entities list in ProcessFactory::make.
        $configured = (array) config('delayed-process.allowed_entities', []);
        if (! in_array($entity, $configured, true)) {
            $configured[] = $entity;
            config()->set('delayed-process.allowed_entities', $configured);
        }
    }

    /**
     * @param  class-string  $entity
     */
    public function isAllowed(string $entity, string $method): bool
    {
        return isset($this->allowed[$entity])
            && in_array($method, $this->allowed[$entity], true);
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->allowed;
    }

    public function clear(): void
    {
        $this->allowed = [];
    }
}

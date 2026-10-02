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
 * A pair is registered from a service provider's or a plugin's boot():
 *
 *     app(AllowlistRegistrar::class)->allow(ReportBuilder::class, 'build', 'admin.reports.build');
 *
 * The optional permission is required from whoever starts the handler. On top
 * of it, an AsyncAction that starts the handler with its own permission() or
 * canSee() is honoured too: see DelayedProcessController::run.
 */
final class AllowlistRegistrar
{
    /** @var array<string, list<string>> entity FQCN => list of method names */
    private array $allowed = [];

    /** @var array<string, list<string>> `entity::method` => the permissions it requires */
    private array $permissions = [];

    /**
     * @param  class-string  $entity
     * @param  list<string>|string|null  $permission  Every listed key is required to start it.
     */
    public function allow(string $entity, string $method, array|string|null $permission = null): void
    {
        if (! class_exists($entity)) {
            throw new InvalidArgumentException("Allowed async entity `{$entity}` does not exist");
        }

        $existing = $this->allowed[$entity] ?? [];
        if (! in_array($method, $existing, true)) {
            $existing[] = $method;
        }
        $this->allowed[$entity] = $existing;

        $required = \Dskripchenko\LaravelAdmin\Permission\PermissionCheck::normalize($permission);
        if ($required !== []) {
            $key = $entity.'::'.$method;
            $this->permissions[$key] = array_values(array_unique([...($this->permissions[$key] ?? []), ...$required]));
        }

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
     * The permissions registered for the pair, all of which are required.
     *
     * @return list<string>
     */
    public function permissionsFor(string $entity, string $method): array
    {
        return $this->permissions[$entity.'::'.$method] ?? [];
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
        $this->permissions = [];
    }
}

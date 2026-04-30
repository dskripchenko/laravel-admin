<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\DelayedProcess;

use InvalidArgumentException;

/**
 * Whitelist разрешённых {entity::method} пар для запуска через async-action API.
 *
 * Без этого SPA мог бы инстанцировать любой класс — security risk. Регистрируем
 * явно, какие handler'ы разрешены, и проверяем при запуске async-action.
 *
 * Регистрация — через `Admin::allowAsync($entity, $method)` или из плагина
 * (AdminPlugin::boot).
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

        // Синхронизация с delayed-process config'ом — он валидирует через
        // собственный список allowed_entities в ProcessFactory::make.
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

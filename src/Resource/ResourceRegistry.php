<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use Dskripchenko\LaravelAdmin\Resource\Resource as ResourceBase;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/**
 * Регистр всех Resource-классов.
 *
 * Singleton, биндится в DI. `Admin::resources([...])` под капотом использует
 * `addMany()`. Хранит slug → FQCN. Resolver через DI-контейнер для DI-инъекций
 * в Resource-классы.
 *
 * Внутри namespace `Resource` сам класс `Resource` импортируем как `ResourceBase`,
 * чтобы Pint не нормализовал `class-string<Resource>` к `class-string<resource>`
 * (PHP считает `resource` pseudo-type для file-handle).
 */
final class ResourceRegistry
{
    /** @var array<string, class-string<ResourceBase>> slug => FQCN */
    private array $resources = [];

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<ResourceBase>  $class
     */
    public function add(string $class): void
    {
        if (! is_subclass_of($class, ResourceBase::class)) {
            throw new InvalidArgumentException(
                "{$class} must extend ".ResourceBase::class,
            );
        }

        $slug = $class::slug();

        if (isset($this->resources[$slug]) && $this->resources[$slug] !== $class) {
            throw new RuntimeException(
                "Resource slug `{$slug}` already taken by {$this->resources[$slug]}; cannot register {$class}",
            );
        }

        $this->resources[$slug] = $class;
    }

    /**
     * @param  list<class-string<ResourceBase>>  $classes
     */
    public function addMany(array $classes): void
    {
        foreach ($classes as $class) {
            $this->add($class);
        }
    }

    public function has(string $slug): bool
    {
        return isset($this->resources[$slug]);
    }

    /**
     * @return class-string<ResourceBase>|null
     */
    public function get(string $slug): ?string
    {
        return $this->resources[$slug] ?? null;
    }

    /**
     * Resolve Resource-instance через DI.
     */
    public function resolve(string $slug): ?ResourceBase
    {
        $class = $this->get($slug);
        if ($class === null) {
            return null;
        }

        /** @var ResourceBase $instance */
        $instance = $this->app->make($class);

        return $instance;
    }

    /**
     * @return array<string, class-string<ResourceBase>>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->resources);
    }

    public function clear(): void
    {
        $this->resources = [];
    }
}

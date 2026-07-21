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

    /** @var array<string, string> slug => panel id */
    private array $panels = [];

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<ResourceBase>  $class
     */
    public function add(string $class, string $panel = 'admin'): void
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
        $this->panels[$slug] = $panel;
    }

    /**
     * @param  list<class-string<ResourceBase>>  $classes
     */
    public function addMany(array $classes, string $panel = 'admin'): void
    {
        foreach ($classes as $class) {
            $this->add($class, $panel);
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
    public function resolve(string $slug, ?string $panel = null): ?ResourceBase
    {
        $class = $this->get($slug);
        if ($class === null) {
            return null;
        }
        if ($panel !== null && ($this->panels[$slug] ?? 'admin') !== $panel) {
            return null;
        }

        /** @var ResourceBase $instance */
        $instance = $this->app->make($class);

        return $instance;
    }

    /**
     * Без аргумента — все ресурсы (BC); с панелью — только её скоуп.
     *
     * @return array<string, class-string<ResourceBase>>
     */
    public function all(?string $panel = null): array
    {
        if ($panel === null) {
            return $this->resources;
        }

        return array_filter(
            $this->resources,
            fn (string $slug): bool => ($this->panels[$slug] ?? 'admin') === $panel,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function panelOf(string $slug): ?string
    {
        return isset($this->resources[$slug]) ? ($this->panels[$slug] ?? 'admin') : null;
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
        $this->panels = [];
    }
}

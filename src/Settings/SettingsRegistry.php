<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/**
 * Registry SettingsResource'ов. Хранит slug → FQCN.
 */
final class SettingsRegistry
{
    /** @var array<string, class-string<SettingsResource>> */
    private array $resources = [];

    /** @var array<string, string> slug => panel id */
    private array $panels = [];

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<SettingsResource>  $class
     */
    public function add(string $class, string $panel = 'admin'): void
    {
        if (! is_subclass_of($class, SettingsResource::class)) {
            throw new InvalidArgumentException(
                "{$class} must extend ".SettingsResource::class,
            );
        }

        $slug = $class::slug();
        if (isset($this->resources[$slug]) && $this->resources[$slug] !== $class) {
            throw new RuntimeException(
                "Settings slug `{$slug}` already registered for {$this->resources[$slug]}",
            );
        }

        $this->resources[$slug] = $class;
        $this->panels[$slug] = $panel;
    }

    /**
     * @param  list<class-string<SettingsResource>>  $classes
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

    public function resolve(string $slug, ?string $panel = null): ?SettingsResource
    {
        $class = $this->resources[$slug] ?? null;
        if ($class === null) {
            return null;
        }
        if ($panel !== null && ($this->panels[$slug] ?? 'admin') !== $panel) {
            return null;
        }

        /** @var SettingsResource $instance */
        $instance = $this->app->make($class);

        return $instance;
    }

    /**
     * Без аргумента — все (BC); с панелью — только её скоуп.
     *
     * @return array<string, class-string<SettingsResource>>
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

    public function clear(): void
    {
        $this->resources = [];
        $this->panels = [];
    }
}

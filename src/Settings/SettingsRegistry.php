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

    public function __construct(private readonly Application $app) {}

    /**
     * @param  class-string<SettingsResource>  $class
     */
    public function add(string $class): void
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
    }

    /**
     * @param  list<class-string<SettingsResource>>  $classes
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

    public function resolve(string $slug): ?SettingsResource
    {
        $class = $this->resources[$slug] ?? null;
        if ($class === null) {
            return null;
        }

        /** @var SettingsResource $instance */
        $instance = $this->app->make($class);

        return $instance;
    }

    /**
     * @return array<string, class-string<SettingsResource>>
     */
    public function all(): array
    {
        return $this->resources;
    }

    public function clear(): void
    {
        $this->resources = [];
    }
}

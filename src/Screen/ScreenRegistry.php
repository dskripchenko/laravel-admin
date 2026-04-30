<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use InvalidArgumentException;
use RuntimeException;

/**
 * Регистр всех зарегистрированных Screen-классов.
 *
 * Singleton, биндится в DI как Screen\ScreenRegistry. Resolver — slug → FQCN.
 * `Admin::screen($class)` под капотом дёргает `add($class)`.
 */
final class ScreenRegistry
{
    /** @var array<string, class-string<Screen>> slug => FQCN */
    private array $screens = [];

    /**
     * @param  class-string<Screen>  $class
     */
    public function add(string $class): void
    {
        if (! is_subclass_of($class, Screen::class)) {
            throw new InvalidArgumentException(
                "{$class} must extend ".Screen::class,
            );
        }

        $slug = $class::slug();

        if (isset($this->screens[$slug]) && $this->screens[$slug] !== $class) {
            throw new RuntimeException(
                "Screen slug `{$slug}` already taken by {$this->screens[$slug]}; cannot register {$class}",
            );
        }

        $this->screens[$slug] = $class;
    }

    /**
     * @param  list<class-string<Screen>>  $classes
     */
    public function addMany(array $classes): void
    {
        foreach ($classes as $class) {
            $this->add($class);
        }
    }

    public function has(string $slug): bool
    {
        return isset($this->screens[$slug]);
    }

    /**
     * @return class-string<Screen>|null
     */
    public function get(string $slug): ?string
    {
        return $this->screens[$slug] ?? null;
    }

    /**
     * @return array<string, class-string<Screen>>
     */
    public function all(): array
    {
        return $this->screens;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->screens);
    }

    public function clear(): void
    {
        $this->screens = [];
    }
}

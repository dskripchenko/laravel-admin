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

    /** @var array<string, string> slug => panel id */
    private array $panels = [];

    /**
     * @param  class-string<Screen>  $class
     */
    public function add(string $class, string $panel = 'admin'): void
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
        $this->panels[$slug] = $panel;
    }

    /**
     * @param  list<class-string<Screen>>  $classes
     */
    public function addMany(array $classes, string $panel = 'admin'): void
    {
        foreach ($classes as $class) {
            $this->add($class, $panel);
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
     * Без аргумента — все экраны (BC); с панелью — только её скоуп.
     *
     * @return array<string, class-string<Screen>>
     */
    public function all(?string $panel = null): array
    {
        if ($panel === null) {
            return $this->screens;
        }

        return array_filter(
            $this->screens,
            fn (string $slug): bool => ($this->panels[$slug] ?? 'admin') === $panel,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function panelOf(string $slug): ?string
    {
        return isset($this->screens[$slug]) ? ($this->panels[$slug] ?? 'admin') : null;
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
        $this->panels = [];
    }
}

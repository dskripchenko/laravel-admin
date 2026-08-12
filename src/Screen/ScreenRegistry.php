<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Screen;

use InvalidArgumentException;
use RuntimeException;

/**
 * The registry of every registered screen class.
 *
 * A singleton, bound in the container as Screen\ScreenRegistry; it resolves a
 * slug to an FQCN. `Admin::screen($class)` calls `add($class)` underneath.
 */
final class ScreenRegistry
{
    /** @var array<string, class-string<Screen>> slug => FQCN */
    private array $screens = [];

    /**
     * One screen may serve several panels: the slug is the key, the panels are
     * a list. A single value used to live here, and registering the same class
     * into a second panel silently MOVED it out of the first — no error, no
     * warning, the section just vanished from a panel that used to have it.
     *
     * @var array<string, list<string>> slug => panel ids
     */
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

        if (! in_array($panel, $this->panels[$slug] ?? [], true)) {
            $this->panels[$slug][] = $panel;
        }
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
     * Without an argument: every screen; with a panel: that panel's scope alone.
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
            fn (string $slug): bool => in_array($panel, $this->panels[$slug] ?? ['admin'], true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The FIRST panel the screen was registered into. Kept for callers that
     * predate multi-panel screens; use `panelsOf()` when the answer matters.
     */
    public function panelOf(string $slug): ?string
    {
        if (! isset($this->screens[$slug])) {
            return null;
        }

        return ($this->panels[$slug] ?? [])[0] ?? 'admin';
    }

    /**
     * @return list<string>
     */
    public function panelsOf(string $slug): array
    {
        if (! isset($this->screens[$slug])) {
            return [];
        }

        return $this->panels[$slug] ?? ['admin'];
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

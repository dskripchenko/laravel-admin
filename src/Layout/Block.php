<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * A card with a title, a description and a call to action around a group of renderables.
 *
 * The SPA renders it as a `<UiCard>` with the `title`, `description` and `actions` slots.
 */
final class Block extends Layout
{
    /**
     * @param  list<Renderable>  $children
     */
    public static function make(string $title, array $children = []): self
    {
        $instance = new self;
        $instance->props['title'] = $title;
        $instance->children = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'block';
    }

    public function description(string $description): self
    {
        $this->props['description'] = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->props['icon'] = $icon;

        return $this;
    }

    /**
     * @param  list<Renderable>  $actions
     */
    public function actions(array $actions): self
    {
        $this->props['actions'] = array_map(static fn (Renderable $a): array => $a->toArray(), $actions);

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * A semantic wrapper with no styling of its own.
 *
 * It is useful for putting several children under one visibility or permission
 * condition, or as the place to reuse a custom Vue wrapper component.
 *
 * @method $this className(string $class)
 */
final class Wrapper extends Layout
{
    /**
     * @param  list<Renderable>  $children
     */
    public static function make(array $children = []): self
    {
        $instance = new self;
        $instance->children = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'wrapper';
    }

    public function tag(string $tag): self
    {
        $this->props['tag'] = $tag;

        return $this;
    }
}

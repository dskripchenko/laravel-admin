<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * A semantic wrapper with no styling of its own.
 *
 * It is useful for putting several children under one visibility or permission
 * condition, or as the place to put a host CSS class around a group.
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

    /**
     * The CSS class (or classes) of the wrapping element.
     */
    public function className(string $class): self
    {
        $this->props['className'] = $class;

        return $this;
    }

    /**
     * The wrapping element: div (the default), section, article, aside,
     * header, footer, main, nav, fieldset or span.
     */
    public function tag(string $tag): self
    {
        $this->props['tag'] = $tag;

        return $this;
    }
}

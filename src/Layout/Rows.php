<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * A simple form of vertically stacked fields and layouts.
 *
 * The SPA renders it as a `<UiForm>` with the list of fields.
 */
final class Rows extends Layout
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
        return 'rows';
    }

    /**
     * @param  list<Renderable>  $children
     */
    public function withChildren(array $children): self
    {
        $this->children = $children;

        return $this;
    }
}

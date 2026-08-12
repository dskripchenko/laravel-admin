<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * A horizontal split: several columns side by side.
 *
 * The ratios for the CSS grid are optional (`->ratios([1, 2])`); without them
 * the columns are equal.
 */
final class Columns extends Layout
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
        return 'columns';
    }

    /**
     * @param  list<int|string>  $ratios  The values of the CSS grid's template-columns.
     */
    public function ratios(array $ratios): self
    {
        $this->props['ratios'] = $ratios;

        return $this;
    }

    public function gap(int $gap): self
    {
        $this->props['gap'] = $gap;

        return $this;
    }
}

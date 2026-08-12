<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use InvalidArgumentException;

/**
 * A drawer — a panel sliding in from the right, left, top or bottom.
 *
 * It suits a quick edit or an inline detail view, with no trip to a separate screen.
 */
final class Drawer extends Layout
{
    private const ALLOWED_POSITIONS = ['left', 'right', 'top', 'bottom'];

    /**
     * @param  list<Renderable>  $children
     */
    public static function make(string $title = '', array $children = []): self
    {
        $instance = new self;
        $instance->props['title'] = $title;
        $instance->props['position'] = 'right';
        $instance->children = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'drawer';
    }

    public function position(string $position): self
    {
        if (! in_array($position, self::ALLOWED_POSITIONS, true)) {
            throw new InvalidArgumentException(
                'Drawer position must be one of: '.implode(', ', self::ALLOWED_POSITIONS),
            );
        }

        $this->props['position'] = $position;

        return $this;
    }

    public function size(string $size): self
    {
        $this->props['size'] = $size;

        return $this;
    }
}

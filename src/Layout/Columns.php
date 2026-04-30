<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Горизонтальное деление: несколько колонок side-by-side.
 *
 * Опциональные ratio для CSS-grid (`->ratios([1, 2])`). Без ratios —
 * равные колонки.
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
     * @param  list<int|string>  $ratios  CSS-grid template-columns значения.
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

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Семантическая обёртка без визуального оформления.
 *
 * Полезна для группировки нескольких children в одно условие visibility/permission,
 * либо как точка ре-использования custom Vue-компонента-обёртки.
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

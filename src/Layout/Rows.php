<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Простая форма из вертикально расположенных Field/Layout элементов.
 *
 * SPA рендерит как `<UiForm>` со списком полей.
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

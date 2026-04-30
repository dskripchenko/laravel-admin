<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Modal — overlay-окно поверх текущего screen'а.
 *
 * SPA рендерит как dialog: close-кнопка, optional title/footer, размер.
 * Открывается через Action или ScreenRouter.
 */
final class Modal extends Layout
{
    /**
     * @param  list<Renderable>  $children
     */
    public static function make(string $title = '', array $children = []): self
    {
        $instance = new self;
        $instance->props['title'] = $title;
        $instance->children = $children;

        return $instance;
    }

    public function type(): string
    {
        return 'modal';
    }

    /**
     * 'sm' | 'md' | 'lg' | 'xl' | 'full'.
     */
    public function size(string $size): self
    {
        $this->props['size'] = $size;

        return $this;
    }

    public function dismissable(bool $dismissable = true): self
    {
        $this->props['dismissable'] = $dismissable;

        return $this;
    }

    /**
     * @param  list<Renderable>  $actions
     */
    public function footer(array $actions): self
    {
        $this->props['footer'] = array_map(
            static fn (Renderable $a): array => $a->toArray(),
            $actions,
        );

        return $this;
    }
}

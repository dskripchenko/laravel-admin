<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * An arbitrary Vue component, by name, with props.
 *
 * The extension point for custom layouts: one registers a Vue component in the
 * SPA under a name, and the layout is rendered through `<component :is>`.
 */
final class View extends Layout
{
    /**
     * @param  array<string, mixed>  $props
     */
    public static function make(string $component, array $props = []): self
    {
        $instance = new self;
        $instance->props = array_merge($props, ['component' => $component]);

        return $instance;
    }

    public function type(): string
    {
        return 'view';
    }
}

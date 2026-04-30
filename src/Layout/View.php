<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * Произвольный Vue-компонент по имени с props.
 *
 * Точка расширения для кастомных layout'ов: пользователь регистрирует
 * Vue-компонент в SPA по имени, layout рендерится через `<component :is>`.
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

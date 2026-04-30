<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;

/**
 * Табы. Принимает map `label => layout|fields[]`.
 *
 * Если значение — массив, оборачивается в Rows автоматически.
 */
final class Tabs extends Layout
{
    /**
     * @param  array<string, Renderable|list<Renderable>>  $tabs
     */
    public static function make(array $tabs = []): self
    {
        $instance = new self;
        $instance->props['labels'] = [];

        foreach ($tabs as $label => $content) {
            $instance->props['labels'][] = $label;
            $instance->children[] = $content instanceof Renderable
                ? $content
                : Rows::make($content);
        }

        return $instance;
    }

    public function type(): string
    {
        return 'tabs';
    }

    public function defaultTab(int $index): self
    {
        $this->props['default'] = $index;

        return $this;
    }
}

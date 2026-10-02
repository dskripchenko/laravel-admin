<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Widget\Widget;

/**
 * The dashboard layout — a grid of widgets.
 *
 * The SPA renders a twelve-column grid, where each widget takes widget->size
 * columns. Per-user customization is supported: the dashboard layer is
 * serialized with an order that the SPA saves into admin_dashboard_layouts.
 */
final class Dashboard extends Layout
{
    /**
     * @param  list<Widget>  $widgets
     */
    public static function make(array $widgets = []): self
    {
        $instance = new self;
        foreach ($widgets as $widget) {
            $instance->add($widget);
        }

        return $instance;
    }

    public function type(): string
    {
        return 'dashboard';
    }

    /**
     * Adds a widget. One whose slug an earlier widget already has — a second
     * chart, say — gets a distinct one, see Widget::distinctSlugs().
     */
    public function add(Widget $widget): self
    {
        $placed = array_values(array_filter($this->children, static fn (mixed $c): bool => $c instanceof Widget));
        Widget::distinctSlugs([...$placed, $widget]);
        $this->children[] = $widget;

        return $this;
    }

    /**
     * The grid's columns — usually 12, as in Bootstrap, or 24 for a finer grain.
     */
    public function gridColumns(int $columns): self
    {
        $this->props['gridColumns'] = $columns;

        return $this;
    }

    /**
     * The gap between the cells, in pixels or as a Tailwind class.
     */
    public function gap(string $gap): self
    {
        $this->props['gap'] = $gap;

        return $this;
    }

    /**
     * The dashboard's name, which doubles as the `persistKey` of the per-user customization.
     */
    public function key(string $key): self
    {
        $this->props['key'] = $key;

        return $this;
    }
}

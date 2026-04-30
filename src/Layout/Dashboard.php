<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Widget\Widget;

/**
 * Dashboard layout — сетка из widgets.
 *
 * SPA рендерит как 12-колоночный grid; каждый widget занимает widget->size
 * колонок. Поддерживает per-user customization (P8.3) — слой dashboard
 * сериализуется с порядком, который SPA сохраняет в admin_dashboard_layouts.
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
            $instance->children[] = $widget;
        }

        return $instance;
    }

    public function type(): string
    {
        return 'dashboard';
    }

    public function add(Widget $widget): self
    {
        $this->children[] = $widget;

        return $this;
    }

    /**
     * Колонок в сетке — обычно 12 (Bootstrap-like) или 24 (более fine-grained).
     */
    public function gridColumns(int $columns): self
    {
        $this->props['gridColumns'] = $columns;

        return $this;
    }

    /**
     * Отступ между ячейками (px / Tailwind class).
     */
    public function gap(string $gap): self
    {
        $this->props['gap'] = $gap;

        return $this;
    }

    /**
     * Имя dashboard'а — используется как `persistKey` для per-user customization.
     */
    public function key(string $key): self
    {
        $this->props['key'] = $key;

        return $this;
    }
}

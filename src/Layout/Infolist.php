<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\Infolist\Entry;

/**
 * Layout для отображения read-only записи через Entry-список.
 *
 * Используется в GeneratedViewScreen и custom view-screen'ах. Каждый entry
 * — read-only display одного поля; SPA рендерит как definition-list или
 * grid в зависимости от layout('rows'|'columns').
 */
final class Infolist extends Layout
{
    /**
     * @param  list<Entry>  $entries
     */
    public static function make(array $entries = []): self
    {
        $instance = new self;
        foreach ($entries as $entry) {
            $instance->children[] = $entry;
        }

        return $instance;
    }

    public function type(): string
    {
        return 'infolist';
    }

    /**
     * 'rows' (default) | 'columns' | 'grid'.
     */
    public function layout(string $layout): self
    {
        $this->props['layout'] = $layout;

        return $this;
    }

    public function gridColumns(int $columns): self
    {
        $this->props['columns'] = $columns;

        return $this;
    }

    /**
     * Добавить entry/secondary layout fluently.
     */
    public function add(Renderable $child): self
    {
        $this->children[] = $child;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Dskripchenko\LaravelAdmin\Infolist\Entry;

/**
 * The layout showing a record read-only, through a list of entries.
 *
 * It is used by GeneratedViewScreen and by custom view screens. Each entry
 * displays one field read-only, and the SPA renders either a definition list
 * or a grid, depending on layout('rows'|'columns').
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
     * Adds an entry or a secondary layout, fluently.
     */
    public function add(Renderable $child): self
    {
        $this->children[] = $child;

        return $this;
    }
}

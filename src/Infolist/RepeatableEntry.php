<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Displays a collection — the counterpart of Field\Repeater. Each item is
 * rendered through the nested entries.
 */
final class RepeatableEntry extends Entry
{
    public function entryType(): string
    {
        return 'repeatable';
    }

    /**
     * @param  list<Entry>  $entries
     */
    public function entries(array $entries): static
    {
        $this->attributes['entries'] = array_map(
            static fn (Entry $e): array => $e->toArray(),
            $entries,
        );

        return $this;
    }

    /**
     * One item's layout: 'rows' (the default), 'columns' or 'inline'.
     */
    public function layout(string $layout): static
    {
        $this->attributes['layout'] = $layout;

        return $this;
    }
}

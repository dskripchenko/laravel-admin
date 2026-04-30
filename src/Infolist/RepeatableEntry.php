<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Display коллекции — пар к Field\Repeater. Каждый item рендерится через
 * вложенные entries.
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
     * Layout одного item'а: 'rows' (default) | 'columns' | 'inline'.
     */
    public function layout(string $layout): static
    {
        $this->attributes['layout'] = $layout;

        return $this;
    }
}

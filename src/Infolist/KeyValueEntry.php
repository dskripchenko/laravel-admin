<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Read-only display ассоциативного массива. Парный к Field\KeyValue.
 */
final class KeyValueEntry extends Entry
{
    public function entryType(): string
    {
        return 'key_value';
    }

    public function keyLabel(string $label): static
    {
        $this->attributes['keyLabel'] = $label;

        return $this;
    }

    public function valueLabel(string $label): static
    {
        $this->attributes['valueLabel'] = $label;

        return $this;
    }
}

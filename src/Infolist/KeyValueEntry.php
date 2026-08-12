<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * A read-only display of an associative array; the counterpart of Field\KeyValue.
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

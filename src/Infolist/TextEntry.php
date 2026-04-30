<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Простой текст. С опциональными copy/link/format-presetами.
 */
final class TextEntry extends Entry
{
    public function entryType(): string
    {
        return 'text';
    }

    public function copyable(bool $copyable = true): static
    {
        $this->attributes['copyable'] = $copyable;

        return $this;
    }

    public function asDate(string $format = 'Y-m-d'): static
    {
        $this->attributes['preset'] = 'date';
        $this->attributes['format'] = $format;

        return $this;
    }

    public function asDateTime(string $format = 'Y-m-d H:i:s'): static
    {
        $this->attributes['preset'] = 'datetime';
        $this->attributes['format'] = $format;

        return $this;
    }

    public function asMoney(string $currency = 'RUB', int $decimals = 2): static
    {
        $this->attributes['preset'] = 'money';
        $this->attributes['currency'] = $currency;
        $this->attributes['decimals'] = $decimals;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Plain text, with optional copy, link and format presets.
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
        $this->attributes['meta'] = ['format' => $format];

        return $this;
    }

    public function asDateTime(string $format = 'Y-m-d H:i:s'): static
    {
        $this->attributes['preset'] = 'datetime';
        $this->attributes['format'] = $format;
        $this->attributes['meta'] = ['format' => $format];

        return $this;
    }

    public function asMoney(string $currency = 'RUB', int $decimals = 2): static
    {
        $this->attributes['preset'] = 'money';
        $this->attributes['currency'] = $currency;
        $this->attributes['decimals'] = $decimals;
        // The formatter's settings, also as the `meta` the cell formatter
        // reads: the top-level infolist folds currency and decimals into it,
        // but an entry nested in a RepeatableEntry is spread as it is — and
        // lost them, every amount showing in the default currency.
        $this->attributes['meta'] = ['currency' => $currency, 'decimals' => $decimals];

        return $this;
    }
}

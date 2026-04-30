<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Цветной swatch + hex/rgb значение рядом.
 */
final class ColorEntry extends Entry
{
    public function entryType(): string
    {
        return 'color';
    }

    public function format(string $format): static
    {
        $this->attributes['format'] = $format;

        return $this;
    }

    public function showValue(bool $show = true): static
    {
        $this->attributes['showValue'] = $show;

        return $this;
    }
}

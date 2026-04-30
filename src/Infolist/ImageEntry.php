<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Превью картинки.
 */
final class ImageEntry extends Entry
{
    public function entryType(): string
    {
        return 'image';
    }

    public function size(?int $width, ?int $height): static
    {
        $this->attributes['width'] = $width;
        $this->attributes['height'] = $height;

        return $this;
    }

    public function rounded(bool $rounded = true): static
    {
        $this->attributes['rounded'] = $rounded;

        return $this;
    }

    public function clickToZoom(bool $zoom = true): static
    {
        $this->attributes['clickToZoom'] = $zoom;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Иконка вместо текста (для статусов, типов).
 */
final class IconEntry extends Entry
{
    public function entryType(): string
    {
        return 'icon';
    }

    /**
     * Маппинг value → имя иконки.
     *
     * @param  array<string, string>  $iconMap
     */
    public function icons(array $iconMap): static
    {
        $this->attributes['icons'] = $iconMap;

        return $this;
    }

    public function size(string $size): static
    {
        $this->attributes['size'] = $size;

        return $this;
    }
}

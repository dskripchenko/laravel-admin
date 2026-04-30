<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * Бэйдж с цветом по value (`active` → green, `banned` → red).
 */
final class BadgeEntry extends Entry
{
    public function entryType(): string
    {
        return 'badge';
    }

    /**
     * @param  array<string, string>  $colorMap  value => UI color name
     */
    public function colors(array $colorMap): static
    {
        $this->attributes['colors'] = $colorMap;

        return $this;
    }

    /**
     * @param  array<string, string>  $labels  value => human label
     */
    public function labels(array $labels): static
    {
        $this->attributes['labels'] = $labels;

        return $this;
    }
}

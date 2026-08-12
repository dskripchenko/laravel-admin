<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * An icon instead of text, for statuses and types.
 */
final class IconEntry extends Entry
{
    public function entryType(): string
    {
        return 'icon';
    }

    /**
     * Maps a value to an icon's name.
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

    /* -----------------------------------------------------------------
     * The boolean mode: trueIcon/falseIcon plus trueLabel/falseLabel.
     *
     * An alternative to {@see icons()}: for a two-valued flag the frontend
     * (IconEntry.vue) shows an icon and an optional text, depending on the
     * value's truthiness. The default Resource::infolist() uses it for the
     * `switch` fields.
     * ----------------------------------------------------------------- */

    public function trueIcon(string $icon): static
    {
        $this->attributes['trueIcon'] = $icon;

        return $this;
    }

    public function falseIcon(string $icon): static
    {
        $this->attributes['falseIcon'] = $icon;

        return $this;
    }

    public function trueLabel(string $label): static
    {
        $this->attributes['trueLabel'] = $label;

        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->attributes['falseLabel'] = $label;

        return $this;
    }
}

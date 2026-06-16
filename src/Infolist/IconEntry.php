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

    /* -----------------------------------------------------------------
     * Boolean-режим (trueIcon/falseIcon + trueLabel/falseLabel).
     *
     * Альтернатива {@see icons()}: для двухзначных флагов фронт
     * (IconEntry.vue) показывает иконку + опциональный текст в
     * зависимости от truthiness значения. Используется в дефолтном
     * Resource::infolist() для `switch`-полей.
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

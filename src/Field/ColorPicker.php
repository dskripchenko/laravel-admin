<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use InvalidArgumentException;

/**
 * Color picker.
 *
 * format: 'hex' | 'rgb' | 'hsl'. Дефолт — 'hex'.
 */
final class ColorPicker extends Field
{
    private const ALLOWED_FORMATS = ['hex', 'rgb', 'hsl'];

    public function fieldType(): string
    {
        return 'color';
    }

    public function format(string $format): static
    {
        if (! in_array($format, self::ALLOWED_FORMATS, true)) {
            throw new InvalidArgumentException(
                'Color format must be one of: '.implode(', ', self::ALLOWED_FORMATS),
            );
        }

        $this->attributes['format'] = $format;

        return $this;
    }

    /**
     * Преднастроенная палитра.
     *
     * @param  list<string>  $colors
     */
    public function palette(array $colors): static
    {
        $this->attributes['palette'] = $colors;

        return $this;
    }

    public function withAlpha(bool $withAlpha = true): static
    {
        $this->attributes['withAlpha'] = $withAlpha;

        return $this;
    }
}

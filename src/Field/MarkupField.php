<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use InvalidArgumentException;

/**
 * What the kanban and calendar fields share: the value is the markup of the
 * kit's UidKanban / UidEventCalendar — the same text the screen layouts and
 * the ```kanban / ```calendar blocks read — kept in a text column and edited
 * in place. The server checks a string of at most maxLength() characters.
 *
 * There is no lock: the field's readonly() or disabled() makes it read-only.
 *
 * @method $this readonly(bool $readonly = true)
 * @method $this disabled(bool $disabled = true)
 */
abstract class MarkupField extends Field
{
    /** The default length cap, in characters: what a TEXT column holds. */
    public const DEFAULT_MAX_LENGTH = 65535;

    /** The height of the box: 560 (px) or '70vh'; it grows with the content when not set. */
    public function height(int|string $height): static
    {
        $this->attributes['height'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    /** The longest markup the server accepts, in characters; 65 535 by default. */
    public function maxLength(int $characters): static
    {
        if ($characters <= 0) {
            throw new InvalidArgumentException("{$this->fieldType()}->maxLength() must be positive, {$characters} given");
        }
        $this->attributes['maxLength'] = $characters;

        return $this;
    }

    /** The length cap the rules exporter applies. */
    public function maxLengthRule(): int
    {
        $max = $this->attributes['maxLength'] ?? null;

        return is_int($max) ? $max : self::DEFAULT_MAX_LENGTH;
    }
}

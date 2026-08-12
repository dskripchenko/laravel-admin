<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A time picker: hours, minutes and optionally seconds.
 */
final class TimePicker extends Field
{
    public function fieldType(): string
    {
        return 'time';
    }

    public function format(string $format = 'H:i'): static
    {
        $this->attributes['format'] = $format;

        return $this;
    }

    public function withSeconds(bool $withSeconds = true): static
    {
        $this->attributes['withSeconds'] = $withSeconds;
        if ($withSeconds) {
            $this->attributes['format'] = $this->attributes['format'] ?? 'H:i:s';
        }

        return $this;
    }

    /**
     * The step of the minutes: 1, 5, 15, 30 and so on.
     */
    public function step(int $minutes): static
    {
        $this->attributes['step'] = $minutes;

        return $this;
    }
}

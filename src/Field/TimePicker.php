<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Time picker (часы/минуты/[секунды]).
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
     * Шаг минут — 1/5/15/30/...
     */
    public function step(int $minutes): static
    {
        $this->attributes['step'] = $minutes;

        return $this;
    }
}

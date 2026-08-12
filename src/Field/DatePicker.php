<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use DateTimeInterface;

/**
 * A date picker: day, month and year.
 *
 * `format` is how the value is stored and parsed on the backend, ISO 'Y-m-d'
 * by default. The UI's own format is set separately, through
 * `displayFormat()`, and governs what the SPA shows and accepts.
 */
final class DatePicker extends Field
{
    public function fieldType(): string
    {
        return 'date';
    }

    public function format(string $format = 'Y-m-d'): static
    {
        $this->attributes['format'] = $format;

        return $this;
    }

    public function displayFormat(string $format): static
    {
        $this->attributes['displayFormat'] = $format;

        return $this;
    }

    public function min(string|DateTimeInterface $min): static
    {
        $this->attributes['min'] = self::stringify($min);

        return $this;
    }

    public function max(string|DateTimeInterface $max): static
    {
        $this->attributes['max'] = self::stringify($max);

        return $this;
    }

    public function withTime(bool $withTime = true): static
    {
        $this->attributes['withTime'] = $withTime;
        if ($withTime) {
            $this->attributes['format'] = $this->attributes['format'] ?? 'Y-m-d H:i:s';
        }

        return $this;
    }

    private static function stringify(string|DateTimeInterface $value): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DateTimeInterface::ATOM)
            : $value;
    }
}

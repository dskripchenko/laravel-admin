<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use DateTimeInterface;

/**
 * Date picker (день/месяц/год).
 *
 * format — формат хранения и парсинга на backend (default ISO 'Y-m-d'). UI-формат
 * управляется отдельно через `displayFormat()` (вывод/ввод на стороне SPA).
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

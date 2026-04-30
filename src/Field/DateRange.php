<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Range date picker — две даты (from/to).
 *
 * Сериализация: state как `{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`.
 * SPA сам обеспечивает валидность (from <= to) на UI.
 */
final class DateRange extends Field
{
    public function fieldType(): string
    {
        return 'date_range';
    }

    public function format(string $format = 'Y-m-d'): static
    {
        $this->attributes['format'] = $format;

        return $this;
    }

    public function withTime(bool $withTime = true): static
    {
        $this->attributes['withTime'] = $withTime;

        return $this;
    }

    /**
     * Готовые пресеты ('today', 'last_7_days', ...) — UI-shortcut.
     *
     * @param  list<string>  $presets
     */
    public function presets(array $presets): static
    {
        $this->attributes['presets'] = $presets;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A range date picker — two dates, from and to.
 *
 * It serializes into `{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`, and the SPA
 * keeps from <= to in the UI itself.
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
     * The ready-made presets ('today', 'last_7_days', …) — shortcuts in the UI.
     *
     * @param  list<string>  $presets
     */
    public function presets(array $presets): static
    {
        $this->attributes['presets'] = $presets;

        return $this;
    }
}

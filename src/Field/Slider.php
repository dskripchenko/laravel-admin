<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Slider — числовое поле с UI-ползунком.
 *
 * Совместим с обычным числовым input — backend получает число; UI решает
 * отрендерить как slider с min/max/step.
 */
final class Slider extends Field
{
    public function fieldType(): string
    {
        return 'slider';
    }

    public function min(int|float $min): static
    {
        $this->attributes['min'] = $min;

        return $this;
    }

    public function max(int|float $max): static
    {
        $this->attributes['max'] = $max;

        return $this;
    }

    public function step(int|float $step): static
    {
        $this->attributes['step'] = $step;

        return $this;
    }

    /**
     * Тики на ползунке.
     *
     * @param  array<int|string, string>  $marks  value => label
     */
    public function marks(array $marks): static
    {
        $this->attributes['marks'] = $marks;

        return $this;
    }
}

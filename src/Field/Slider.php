<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A slider — a numeric field with a UI handle.
 *
 * It is compatible with an ordinary numeric input: the backend receives a
 * number, and the UI chooses to draw it as a slider with min, max and step.
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
     * The ticks along the slider.
     *
     * @param  array<int|string, string>  $marks  value => label
     */
    public function marks(array $marks): static
    {
        $this->attributes['marks'] = $marks;

        return $this;
    }
}

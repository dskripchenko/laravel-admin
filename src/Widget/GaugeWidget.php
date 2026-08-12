<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use InvalidArgumentException;

/**
 * A gauge — a half-circle or a donut — showing one value within a min..max
 * range, with coloured zones such as low, medium and high.
 */
class GaugeWidget extends Widget
{
    private float $value = 0;

    private float $min = 0;

    private float $max = 100;

    /** @var list<array{from: float, to: float, color: string}> */
    private array $thresholds = [];

    private string $unit = '';

    public function widgetType(): string
    {
        return 'gauge';
    }

    public function value(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function range(float $min, float $max): static
    {
        if ($max <= $min) {
            throw new InvalidArgumentException('Gauge range max must be > min');
        }
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    /**
     * The coloured zones. For example:
     *
     *     ->threshold(0, 50, 'green')
     *     ->threshold(50, 80, 'yellow')
     *     ->threshold(80, 100, 'red')
     */
    public function threshold(float $from, float $to, string $color): static
    {
        $this->thresholds[] = ['from' => $from, 'to' => $to, 'color' => $color];

        return $this;
    }

    public function unit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'value' => $this->value,
            'min' => $this->min,
            'max' => $this->max,
            'unit' => $this->unit,
            'thresholds' => $this->thresholds,
        ];
    }
}

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

    private ?int $precision = null;

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
     *     ->threshold(0, 50, 'success')
     *     ->threshold(50, 80, 'warning')
     *     ->threshold(80, 100, 'danger')
     *
     * The colour is a tone of the UI kit — success, warning, danger, info,
     * primary, neutral — or one of the colour words green, amber, yellow,
     * orange, red, blue, gray; the SPA draws them with the theme's own
     * colours. Any other CSS colour (#hex, rgb()) is used as it is.
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
     * The number of decimals shown. Unset, a whole value shows none and a
     * fractional one up to two.
     */
    public function precision(int $decimals): static
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException('Gauge precision must be >= 0');
        }
        $this->precision = $decimals;

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
            'precision' => $this->precision,
        ];
    }
}

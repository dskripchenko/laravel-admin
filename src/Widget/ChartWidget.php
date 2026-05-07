<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use InvalidArgumentException;

/**
 * Универсальный chart-виджет: line/bar/pie/doughnut/area/radar.
 *
 * Конкретный chart-engine (Chart.js/ApexCharts/...) выбирается на стороне SPA.
 * Backend шлёт нормализованную структуру: labels[], datasets[].
 */
class ChartWidget extends Widget
{
    private const ALLOWED_TYPES = ['line', 'bar', 'pie', 'doughnut', 'area', 'radar'];

    private string $chartType = 'line';

    /** @var list<string|int> */
    private array $labels = [];

    /** @var list<array{label: string, data: list<int|float>, color?: string}> */
    private array $datasets = [];

    private bool $stacked = false;

    public function widgetType(): string
    {
        return 'chart';
    }

    public function chartType(string $type): static
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                'Chart type must be one of: '.implode(', ', self::ALLOWED_TYPES),
            );
        }

        $this->chartType = $type;

        return $this;
    }

    /**
     * @param  list<string|int>  $labels
     */
    public function labels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * @param  list<int|float>  $data
     */
    public function dataset(string $label, array $data, ?string $color = null): static
    {
        $entry = ['label' => $label, 'data' => $data];
        if ($color !== null) {
            $entry['color'] = $color;
        }
        $this->datasets[] = $entry;

        return $this;
    }

    public function stacked(bool $stacked = true): static
    {
        $this->stacked = $stacked;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'chartType' => $this->chartType,
            'labels' => $this->labels,
            'datasets' => $this->datasets,
            'stacked' => $this->stacked,
        ];
    }
}

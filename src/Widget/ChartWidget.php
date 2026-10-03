<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\I18n\Localize;
use InvalidArgumentException;

/**
 * The general-purpose chart widget: line, bar, pie, doughnut, area, radar.
 *
 * Which charting engine draws it — Chart.js, ApexCharts, another — is the
 * SPA's choice. The backend sends a normalized structure: labels[] and
 * datasets[].
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

    /** @var array{style: string, currency?: string, decimals: int}|null */
    private ?array $format = null;

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
     * Shows the values — tooltips, the data table, the axis ticks — as money
     * in that currency (an ISO 4217 code), formatted by the panel's locale:
     * `$1,591,285` in English, `1 591 285 $` in Russian. The datasets keep
     * the raw numbers.
     */
    public function money(string $currency = 'USD', int $decimals = 0): static
    {
        $this->format = [
            'style' => 'currency',
            'currency' => strtoupper($currency),
            'decimals' => max(0, $decimals),
        ];

        return $this;
    }

    /**
     * The decimals the values are shown with; they are formatted by the
     * panel's locale either way.
     */
    public function precision(int $decimals): static
    {
        $this->format = ['style' => 'decimal', 'decimals' => max(0, $decimals)];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'chartType' => $this->chartType,
            // The axis labels and the dataset captions are translated per
            // request; numeric labels pass through untouched.
            'labels' => array_map(
                static fn (string|int $label): string|int => is_string($label) ? (string) Localize::string($label) : $label,
                $this->labels,
            ),
            'datasets' => array_map(static function (array $dataset): array {
                $dataset['label'] = (string) Localize::string($dataset['label']);

                return $dataset;
            }, $this->datasets),
            'stacked' => $this->stacked,
            // How the SPA shows a value; null — a plain number in the
            // panel's locale.
            'format' => $this->format,
        ];
    }
}

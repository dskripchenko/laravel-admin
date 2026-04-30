<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * Heatmap — двумерная матрица value по (row, col) координатам.
 *
 * Подходит для distribution-визуализаций: активность по дням недели/часам,
 * load-distribution и т.п.
 */
final class HeatmapWidget extends Widget
{
    /** @var list<string> */
    private array $rows = [];

    /** @var list<string> */
    private array $cols = [];

    /** @var array<int, array<int, int|float>> */
    private array $matrix = [];

    private string $colorScale = 'viridis';

    public function widgetType(): string
    {
        return 'heatmap';
    }

    /**
     * @param  list<string>  $rows  Метки строк (например, дни недели).
     * @param  list<string>  $cols  Метки колонок (например, часы).
     */
    public function axes(array $rows, array $cols): static
    {
        $this->rows = $rows;
        $this->cols = $cols;

        return $this;
    }

    /**
     * @param  array<int, array<int, int|float>>  $matrix  rows × cols значения.
     */
    public function matrix(array $matrix): static
    {
        $this->matrix = $matrix;

        return $this;
    }

    /**
     * Имя цветовой шкалы для SPA: 'viridis' | 'magma' | 'plasma' | ...
     */
    public function colorScale(string $scale): static
    {
        $this->colorScale = $scale;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'rows' => $this->rows,
            'cols' => $this->cols,
            'matrix' => $this->matrix,
            'colorScale' => $this->colorScale,
        ];
    }
}

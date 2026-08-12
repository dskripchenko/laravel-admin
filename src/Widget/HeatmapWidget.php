<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * A heatmap — a two-dimensional matrix of values at (row, col).
 *
 * It suits the distributions: activity by weekday and hour, the spread of
 * load, and the like.
 */
class HeatmapWidget extends Widget
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
     * @param  list<string>  $rows  The rows' labels — the days of the week, say.
     * @param  list<string>  $cols  The columns' labels — the hours, say.
     */
    public function axes(array $rows, array $cols): static
    {
        $this->rows = $rows;
        $this->cols = $cols;

        return $this;
    }

    /**
     * @param  array<int, array<int, int|float>>  $matrix  The rows × cols values.
     */
    public function matrix(array $matrix): static
    {
        $this->matrix = $matrix;

        return $this;
    }

    /**
     * The colour scale's name for the SPA: 'viridis' | 'magma' | 'plasma' | …
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

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * A heatmap — a two-dimensional matrix of values at (row, col).
 *
 * It suits the distributions: activity by weekday and hour, the spread of
 * load, and the like. Every column is labelled; a null value is drawn as an
 * empty "no data" cell, distinct from 0 — a day that has not come yet, say.
 */
class HeatmapWidget extends Widget
{
    /** @var list<string> */
    private array $rows = [];

    /** @var list<string> */
    private array $cols = [];

    /** @var array<int, array<int, int|float|string|null>> */
    private array $matrix = [];

    /** @var string|list<string> */
    private string|array $colorScale = 'default';

    private ?float $min = null;

    private ?float $max = null;

    /** @var array{style: string, currency?: string, decimals: int}|null */
    private ?array $format = null;

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
     * @param  array<int, array<int, int|float|string|null>>  $matrix  The rows × cols
     *                                                                 values; null — no data.
     */
    public function matrix(array $matrix): static
    {
        $this->matrix = $matrix;

        return $this;
    }

    /**
     * The colour scale: a name — 'default' (the panel's accent), 'viridis',
     * 'magma', 'plasma', 'inferno', 'blues', 'greens', 'reds' — one CSS
     * colour, or a list of CSS colours as custom stops from low to high.
     *
     * @param  string|array<int, string>  $scale
     */
    public function colorScale(string|array $scale): static
    {
        $this->colorScale = is_array($scale) ? array_values($scale) : $scale;

        return $this;
    }

    /**
     * Fixes the colour domain; by default it runs from the smallest value in
     * the matrix to the largest.
     */
    public function range(int|float|null $min, int|float|null $max = null): static
    {
        $this->min = $min === null ? null : (float) $min;
        $this->max = $max === null ? null : (float) $max;

        return $this;
    }

    /**
     * Shows the values — the tooltips and the legend — as money in that
     * currency (an ISO 4217 code), formatted by the panel's locale.
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
     * The decimals the values are shown with, in the panel's locale.
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
            'rows' => Localize::options($this->rows),
            'cols' => Localize::options($this->cols),
            'matrix' => $this->normalizedMatrix(),
            'colorScale' => $this->colorScale,
            'min' => $this->min,
            'max' => $this->max,
            // How the SPA shows a value; null — a plain number in the
            // panel's locale.
            'format' => $this->format,
        ];
    }

    /**
     * The matrix as lists sized to the axes: a missing cell becomes null, a
     * numeric string a number, anything else null.
     *
     * @return list<list<int|float|null>>
     */
    private function normalizedMatrix(): array
    {
        $rowCount = $this->rows === [] ? count($this->matrix) : count($this->rows);

        $out = [];
        for ($r = 0; $r < $rowCount; $r++) {
            $row = $this->matrix[$r] ?? [];
            $width = $this->cols === [] ? count($row) : count($this->cols);
            $cells = [];
            for ($c = 0; $c < $width; $c++) {
                $value = $row[$c] ?? null;
                $cells[] = match (true) {
                    is_int($value), is_float($value) => $value,
                    is_numeric($value) => $value + 0,
                    default => null,
                };
            }
            $out[] = $cells;
        }

        return $out;
    }
}

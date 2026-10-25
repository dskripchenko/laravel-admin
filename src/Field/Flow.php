<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Rules\FlowModel;

/**
 * A process or state-machine editor — FlowEditor of @dskripchenko/flow — as a
 * form field. The value is the model, a JSON object: store it in a JSON
 * column with an `array` cast. It is submitted with the form like any field
 * and checked by the FlowModel rule (added by the rules exporter).
 *
 *     Flow::make('process')
 *         ->palette(['start', 'state', 'task', 'decision', 'end'])
 *         ->locked(['start'])
 *         ->height(560)
 *
 * @method $this readonly(bool $readonly = true)
 */
final class Flow extends Field
{
    public function fieldType(): string
    {
        return 'flow';
    }

    /**
     * The node types the elements panel offers, in order; every type when not
     * set. The server accepts only these types (and the locked ones) too.
     *
     * @param  array<array-key, string>  $types
     */
    public function palette(array $types): static
    {
        $this->attributes['palette'] = array_values(array_map('strval', $types));

        return $this;
    }

    /**
     * Node types the editor does not let users change or delete.
     *
     * @param  array<array-key, string>  $types
     */
    public function locked(array $types): static
    {
        $this->attributes['locked'] = array_values(array_map('strval', $types));

        return $this;
    }

    /** The editor's height: 560 (px) or '70vh'; 520px by default. */
    public function height(int|string $height): static
    {
        $this->attributes['height'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    /** 'LR', 'TB' or 'auto'. */
    public function direction(string $direction): static
    {
        $this->attributes['direction'] = $direction;

        return $this;
    }

    /** 'orthogonal' or 'smooth'. */
    public function edgeStyle(string $style): static
    {
        $this->attributes['edgeStyle'] = $style;

        return $this;
    }

    public function minimap(bool $on = true): static
    {
        $this->attributes['minimap'] = $on;

        return $this;
    }

    /** The grid nodes snap to, in graph units; 0 turns snapping off. */
    public function snapGrid(int $grid): static
    {
        $this->attributes['snapGrid'] = max(0, $grid);

        return $this;
    }

    /** "Import Mermaid" in the editor's JSON/Mermaid menu; on by default. */
    public function mermaidImport(bool $on = true): static
    {
        $this->attributes['mermaid'] = $on;

        return $this;
    }

    /** The file name of "Export JSON". */
    public function exportName(string $name): static
    {
        $this->attributes['exportName'] = $name;

        return $this;
    }

    /**
     * The rule that checks a submitted model: the shape, and the node types
     * when the field has a palette.
     */
    public function modelRule(): FlowModel
    {
        $palette = $this->attributes['palette'] ?? null;
        $locked = $this->attributes['locked'] ?? [];

        return new FlowModel(is_array($palette)
            ? array_values(array_unique([...$palette, ...(is_array($locked) ? $locked : [])]))
            : null);
    }

    /**
     * The model rule for a validator of your own:
     * `'process' => ['required', Flow::rule()]`.
     *
     * @param  list<string>|null  $types  The node types allowed; any when null.
     */
    public static function rule(?array $types = null): FlowModel
    {
        return new FlowModel($types);
    }
}

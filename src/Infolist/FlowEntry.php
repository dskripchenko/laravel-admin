<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * A stored process model, drawn read-only by FlowView of @dskripchenko/flow:
 * pan, zoom, drill into inline sub-processes, the details of a step.
 *
 *     FlowEntry::make('process')->height(420)
 *
 * The value is the model — an object or its JSON. A Field\Flow on a
 * resource's form is shown this way on its view page by itself.
 */
final class FlowEntry extends Entry
{
    public function entryType(): string
    {
        return 'flow';
    }

    /** The height of the box: 420 (px) or '50vh'; 360px by default. */
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
}

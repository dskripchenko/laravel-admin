<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * What the document entries share — BPMN, DMN, kanban, calendar: the value
 * is the record's text (the model file's XML, the markup), drawn read-only
 * in a box of the given height.
 */
abstract class DocumentEntry extends Entry
{
    /** The height of the box: 420 (px) or '50vh'. */
    public function height(int|string $height): static
    {
        $this->attributes['height'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }
}

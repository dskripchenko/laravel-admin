<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout\Concerns;

/**
 * Elements of a BPMN or DMN model outlined in the viewer.
 *
 * @phpstan-require-extends \Dskripchenko\LaravelAdmin\Layout\Layout
 */
trait HighlightsElements
{
    /**
     * The ids of the elements to outline — the current step of a process,
     * say.
     *
     * @param  array<array-key, string>  $ids
     */
    public function highlight(array $ids): static
    {
        $this->props['highlight'] = array_values(array_map('strval', $ids));

        return $this;
    }
}

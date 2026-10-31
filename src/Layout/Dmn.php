<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Layout\Concerns\HighlightsElements;

/**
 * A DMN 1.x decision model shown read-only by DmnView of @dskripchenko/bpmn:
 * the decision requirements diagram with pan and zoom; a double click on a
 * decision opens its table.
 *
 *     Layout::dmn('rules')                        // $state['rules']
 *         ->decision('Decision_discount')         // opens on this decision's table
 *         ->highlight(['Decision_discount'])
 */
final class Dmn extends DocumentLayout
{
    use HighlightsElements;

    public function type(): string
    {
        return 'dmn';
    }

    /** Opens this decision's logic instead of the diagram. */
    public function decision(string $id): self
    {
        $this->props['decision'] = $id;

        return $this;
    }
}

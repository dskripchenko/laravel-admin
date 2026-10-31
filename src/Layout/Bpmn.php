<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Layout\Concerns\HighlightsElements;

/**
 * A BPMN 2.0 process model — a file of Camunda Modeler, bpmn.io or another
 * modeler — shown read-only by BpmnView of @dskripchenko/bpmn: pan and zoom,
 * the elements to outline.
 *
 *     Layout::bpmn('process_xml')                 // $state['process_xml']
 *         ->highlight(['Task_approve'])
 *         ->showDiagram('Collaboration_1')            // a file with several diagrams
 *     Layout::bpmn('order')->url('/files/order.bpmn')
 */
final class Bpmn extends DocumentLayout
{
    use HighlightsElements;

    public function type(): string
    {
        return 'bpmn';
    }

    /**
     * Which diagram of a file that has several: its id or index. The first by
     * default. (Not `diagram()`: that name is the Layout::diagram() factory.)
     */
    public function showDiagram(string|int $diagram): self
    {
        $this->props['diagram'] = $diagram;

        return $this;
    }
}

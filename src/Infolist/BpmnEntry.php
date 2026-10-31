<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * A stored BPMN 2.0 model, drawn read-only by BpmnView of @dskripchenko/bpmn
 * with pan and zoom.
 *
 *     BpmnEntry::make('process_xml')->height(420)->highlight(['Task_approve'])
 *
 * The value is the file's XML. A Field\Bpmn on a resource's form is shown
 * this way on its view page by itself.
 */
final class BpmnEntry extends DocumentEntry
{
    public function entryType(): string
    {
        return 'bpmn';
    }

    /**
     * The ids of the elements to outline.
     *
     * @param  array<array-key, string>  $ids
     */
    public function highlight(array $ids): static
    {
        $this->attributes['highlight'] = array_values(array_map('strval', $ids));

        return $this;
    }

    /** Which diagram of a file that has several: its id or index. */
    public function diagram(string|int $diagram): static
    {
        $this->attributes['diagram'] = $diagram;

        return $this;
    }
}

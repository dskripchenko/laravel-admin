<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Rules\BpmnModel;

/**
 * A BPMN 2.0 process editor — BpmnEditor of @dskripchenko/bpmn — as a form
 * field: a palette, a context pad, a properties panel, undo, pan and zoom.
 * The value is the file's XML, which Camunda Modeler, bpmn.io and other
 * tools read and write; store it in a text column (MEDIUMTEXT or larger for
 * big models). It is submitted with the form like any field and checked by
 * the BpmnModel rule, added by the rules exporter.
 *
 *     Bpmn::make('process_xml')
 *         ->title('Process')
 *         ->height(640)
 *         ->properties()
 *         ->maxSize(1024 * 1024)
 */
final class Bpmn extends XmlModelField
{
    public function fieldType(): string
    {
        return 'bpmn';
    }

    /** Shows the properties panel from the start. */
    public function properties(bool $on = true): static
    {
        $this->attributes['properties'] = $on;

        return $this;
    }

    /** Which diagram of a file that has several: its id or index. The first by default. */
    public function diagram(string|int $diagram): static
    {
        $this->attributes['diagram'] = $diagram;

        return $this;
    }

    public function modelRule(): BpmnModel
    {
        return new BpmnModel($this->maxBytes());
    }

    /**
     * The model rule for a validator of your own:
     * `'process_xml' => ['required', Bpmn::rule()]`.
     *
     * @param  int  $maxBytes  The largest file accepted, in bytes.
     */
    public static function rule(int $maxBytes = BpmnModel::DEFAULT_MAX_BYTES): BpmnModel
    {
        return new BpmnModel($maxBytes);
    }
}

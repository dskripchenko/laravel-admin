<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

/**
 * A stored DMN decision model, drawn read-only by the package's DmnView
 * (@dskripchenko/bpmn): the decision requirements diagram with pan and zoom;
 * a double click on a decision opens its table.
 *
 *     DmnEntry::make('rules_xml')->decision('Decision_discount')
 *
 * The value is the file's XML. A Field\Dmn on a resource's form is shown
 * this way on its view page by itself.
 */
final class DmnEntry extends DocumentEntry
{
    public function entryType(): string
    {
        return 'dmn';
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

    /** Opens this decision's table instead of the diagram. */
    public function decision(string $id): static
    {
        $this->attributes['decision'] = $id;

        return $this;
    }
}

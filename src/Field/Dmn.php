<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Rules\DmnModel;

/**
 * A DMN decision model editor — DmnEditor of @dskripchenko/bpmn — as a form
 * field: the decision requirements diagram and, one click away, each
 * decision's table or literal expression. The value is the file's XML (DMN
 * 1.1 to 1.5); store it in a text column. It is submitted with the form like
 * any field and checked by the DmnModel rule, added by the rules exporter.
 *
 *     Dmn::make('rules_xml')
 *         ->title('Discount rules')
 *         ->decision('Decision_discount')
 */
final class Dmn extends XmlModelField
{
    public function fieldType(): string
    {
        return 'dmn';
    }

    /** Opens this decision's logic instead of the diagram. */
    public function decision(string $id): static
    {
        $this->attributes['decision'] = $id;

        return $this;
    }

    public function modelRule(): DmnModel
    {
        return new DmnModel($this->maxBytes());
    }

    /**
     * The model rule for a validator of your own:
     * `'rules_xml' => ['required', Dmn::rule()]`.
     *
     * @param  int  $maxBytes  The largest file accepted, in bytes.
     */
    public static function rule(int $maxBytes = DmnModel::DEFAULT_MAX_BYTES): DmnModel
    {
        return new DmnModel($maxBytes);
    }
}

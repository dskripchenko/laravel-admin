<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A read-only display field — static text inside a form.
 *
 * Neither editable nor submitted. Its text comes from `->value()` or from the state, by name.
 */
final class Label extends Field
{
    public function fieldType(): string
    {
        return 'label';
    }
}

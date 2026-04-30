<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Read-only display поле — статический текст в форме.
 *
 * Не editable, не submit-able. Через `->value()` или из state по name.
 */
final class Label extends Field
{
    public function fieldType(): string
    {
        return 'label';
    }
}

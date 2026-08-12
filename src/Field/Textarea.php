<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A multi-line text field.
 *
 * @method $this rows(int $rows)
 * @method $this maxlength(int $maxlength)
 */
final class Textarea extends Field
{
    public function fieldType(): string
    {
        return 'textarea';
    }

    /**
     * Auto-resize; a flag for the SPA.
     */
    public function autosize(bool $autosize = true): static
    {
        $this->attributes['autosize'] = $autosize;

        return $this;
    }
}

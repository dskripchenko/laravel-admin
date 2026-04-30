<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Многострочное текстовое поле.
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
     * Авто-resize. SPA-флаг.
     */
    public function autosize(bool $autosize = true): static
    {
        $this->attributes['autosize'] = $autosize;

        return $this;
    }
}

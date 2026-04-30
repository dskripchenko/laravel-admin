<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * Combobox — Select с автодополнением и возможностью ввести произвольное
 * значение (creatable). На SPA рисуется как input + dropdown.
 */
final class Combobox extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'combobox';
    }

    /**
     * Разрешить ввод значений вне списка options.
     */
    public function creatable(bool $creatable = true): static
    {
        $this->attributes['creatable'] = $creatable;

        return $this;
    }
}

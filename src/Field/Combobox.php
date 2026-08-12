<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * A combobox — a select with autocompletion that also accepts an arbitrary
 * value, which makes it creatable. The SPA draws it as an input plus a
 * dropdown.
 */
final class Combobox extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'combobox';
    }

    /**
     * Allows values outside the list of options.
     */
    public function creatable(bool $creatable = true): static
    {
        $this->attributes['creatable'] = $creatable;

        return $this;
    }
}

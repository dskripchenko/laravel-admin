<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * Checkbox.
 *
 * Without options() it is a single boolean toggle; with options() it becomes
 * a group of checkboxes, a multi-select.
 */
final class Checkbox extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'checkbox';
    }

    public function inline(bool $inline = true): static
    {
        $this->attributes['inline'] = $inline;

        return $this;
    }
}

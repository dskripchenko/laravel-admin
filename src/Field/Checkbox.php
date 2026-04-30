<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * Checkbox.
 *
 * Без options() — single boolean toggle.
 * С options() — группа checkbox'ов (multi-select).
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

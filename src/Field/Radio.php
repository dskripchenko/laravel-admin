<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * Radio-группа (single choice). Опции через ->options([...]) или ->fromEnum(...).
 */
final class Radio extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'radio';
    }

    public function inline(bool $inline = true): static
    {
        $this->attributes['inline'] = $inline;

        return $this;
    }
}

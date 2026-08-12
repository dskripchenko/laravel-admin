<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * A radio group — a single choice. The options come from ->options([...]) or ->fromEnum(...).
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

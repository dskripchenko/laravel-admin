<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;

/**
 * Single/multiple select.
 *
 * @method $this placeholder(string $placeholder)
 */
final class Select extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'select';
    }

    public function searchable(bool $searchable = true): static
    {
        $this->attributes['searchable'] = $searchable;

        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->attributes['clearable'] = $clearable;

        return $this;
    }
}

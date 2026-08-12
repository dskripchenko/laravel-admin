<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A repeating set of fields — for editing a HasMany inline, or any array of
 * objects of one shape.
 *
 * The state is a list<array<string, mixed>>, where each item is the submission
 * of a form built from the given $fields. The SPA renders it as a set of cards
 * with add, duplicate, remove and reorder buttons.
 *
 * @method $this collapsible(bool $collapsible = true)
 * @method $this collapsed(bool $collapsed = true)
 */
final class Repeater extends Field
{
    public function fieldType(): string
    {
        return 'repeater';
    }

    /**
     * @param  list<Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->attributes['fields'] = array_map(
            static fn (Field $f): array => $f->toArray(),
            $fields,
        );

        return $this;
    }

    public function minItems(int $min): static
    {
        $this->attributes['minItems'] = $min;

        return $this;
    }

    public function maxItems(int $max): static
    {
        $this->attributes['maxItems'] = $max;

        return $this;
    }

    public function addable(bool $addable = true): static
    {
        $this->attributes['addable'] = $addable;

        return $this;
    }

    public function removable(bool $removable = true): static
    {
        $this->attributes['removable'] = $removable;

        return $this;
    }

    public function reorderable(bool $reorderable = true): static
    {
        $this->attributes['reorderable'] = $reorderable;

        return $this;
    }

    /**
     * The default state of a new item.
     *
     * @param  array<string, mixed>  $values
     */
    public function defaultItem(array $values): static
    {
        $this->attributes['defaultItem'] = $values;

        return $this;
    }
}

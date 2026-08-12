<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A visual group of fields, with a title and a description.
 *
 * Unlike Layout::block(), a Group is a field-level wrapper: it has a `name`,
 * it appears in the state and it is validated as an object, through Laravel's
 * `array` rule. Use it when the state needs nested fields in one structure:
 *
 *     'address' => [
 *         'city' => '...',
 *         'street' => '...',
 *     ]
 */
final class Group extends Field
{
    public function fieldType(): string
    {
        return 'group';
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

    /**
     * The nested fields' layout: 'rows' (the default), 'columns' or 'inline'.
     */
    public function layout(string $layout): static
    {
        $this->attributes['layout'] = $layout;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->attributes['collapsible'] = $collapsible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): static
    {
        $this->attributes['collapsed'] = $collapsed;
        if ($collapsed) {
            $this->attributes['collapsible'] = true;
        }

        return $this;
    }
}

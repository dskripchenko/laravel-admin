<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Повторяющийся набор полей — для HasMany inline-edit или произвольного
 * массива однотипных объектов.
 *
 * State хранится как list<array<string, mixed>>, где каждый item — это
 * сабмит формы из переданных $fields. SPA рендерит как набор «карточек»
 * с кнопками add/duplicate/remove/reorder.
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
     * Default-state для нового item'а.
     *
     * @param  array<string, mixed>  $values
     */
    public function defaultItem(array $values): static
    {
        $this->attributes['defaultItem'] = $values;

        return $this;
    }
}

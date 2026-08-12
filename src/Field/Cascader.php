<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A cascading selection: two or three linked dropdowns — country → region →
 * city.
 *
 * The state is a list<value> as long as there are levels, one value per level.
 * Each level has its own displayLabel, the step's name in the UI.
 */
final class Cascader extends Field
{
    public function fieldType(): string
    {
        return 'cascader';
    }

    /**
     * @param  list<array{key: string, label: string, options?: list<array{value: mixed, label: string, children?: array<int, mixed>}>}>  $levels
     */
    public function levels(array $levels): static
    {
        $this->attributes['levels'] = $levels;

        return $this;
    }

    /**
     * The full nested tree of options, for every level.
     *
     * @param  list<array{value: mixed, label: string, children?: array<int, mixed>}>  $tree
     */
    public function options(array $tree): static
    {
        $this->attributes['tree'] = $tree;

        return $this;
    }

    /**
     * The separator of the displayed value: 'Russia / Moscow / Moscow'.
     */
    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }

    /**
     * Turns on the ?q= search across every level.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->attributes['searchable'] = $searchable;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A hierarchical selection — categories, sections, an organizational tree.
 *
 * The tree comes either from a `tree([...])` array or from an Eloquent model
 * with a self-relation through a `parent_id` column. The state is a single
 * value, or a list<value> when multiple.
 */
final class TreeSelect extends Field
{
    public function fieldType(): string
    {
        return 'tree_select';
    }

    /**
     * The tree as a nested list:
     *   [{value, label, children: [{value, label, ...}]}, ...]
     *
     * @param  list<array{value: mixed, label: string, children?: array<int, mixed>}>  $tree
     */
    public function tree(array $tree): static
    {
        $this->attributes['tree'] = $tree;

        return $this;
    }

    /**
     * Loads the tree from an Eloquent model with a self-referencing parent_id.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function fromModel(string $model, string $parentColumn = 'parent_id', string $valueColumn = 'id', string $labelColumn = 'name'): static
    {
        $this->attributes['relatedModel'] = $model;
        $this->attributes['parentColumn'] = $parentColumn;
        $this->attributes['valueColumn'] = $valueColumn;
        $this->attributes['labelColumn'] = $labelColumn;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->attributes['multiple'] = $multiple;

        return $this;
    }

    public function checkable(bool $checkable = true): static
    {
        $this->attributes['checkable'] = $checkable;

        return $this;
    }

    /**
     * Whether the parent nodes may be selected; true by default. With false,
     * only the leaves are selectable.
     */
    public function selectableParents(bool $selectable = true): static
    {
        $this->attributes['selectableParents'] = $selectable;

        return $this;
    }
}

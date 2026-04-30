<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Иерархический выбор — категории, рубрики, organizational tree.
 *
 * Источник дерева — массив `tree([...])` или Eloquent-модель с self-relation
 * (`parent_id` колонка). State хранится как value (single) или list<value> (multi).
 */
final class TreeSelect extends Field
{
    public function fieldType(): string
    {
        return 'tree_select';
    }

    /**
     * Дерево как nested list:
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
     * Подгрузить дерево из Eloquent-модели с self-referencing parent_id.
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
     * Разрешить выбор parent-узлов (default true). Если false — selectable
     * только листья.
     */
    public function selectableParents(bool $selectable = true): static
    {
        $this->attributes['selectableParents'] = $selectable;

        return $this;
    }
}

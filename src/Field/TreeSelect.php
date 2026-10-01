<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

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
     * @param  class-string<Model>  $model
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

    /**
     * With `fromModel()` and no explicit `tree()`, the tree is built from the
     * model's rows at serialization time — the SPA's component needs the
     * whole tree to render, and there is no endpoint to fetch it from.
     */
    public function toArray(): array
    {
        if (($this->attributes['tree'] ?? []) === [] && isset($this->attributes['relatedModel'])) {
            $this->attributes['tree'] = $this->buildTreeFromModel();
        }

        return parent::toArray();
    }

    /**
     * Loads the model's rows (up to `$limit`) and nests them by the parent
     * column. A row whose parent is missing from the result becomes a root.
     *
     * @return list<array{value: mixed, label: string, children?: list<array<string, mixed>>}>
     */
    public function buildTreeFromModel(int $limit = 1000): array
    {
        $model = $this->attributes['relatedModel'] ?? null;
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return [];
        }
        $parentColumn = (string) ($this->attributes['parentColumn'] ?? 'parent_id');
        $valueColumn = (string) ($this->attributes['valueColumn'] ?? 'id');
        $labelColumn = (string) ($this->attributes['labelColumn'] ?? 'name');

        try {
            /** @var class-string<Model> $model */
            $rows = $model::query()->limit($limit)->get([$valueColumn, $parentColumn, $labelColumn]);
        } catch (QueryException) {
            return [];
        }

        /** @var array<string, list<array{value: mixed, label: string}>> $byParent */
        $byParent = [];
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row->getAttribute($valueColumn)] = true;
        }
        foreach ($rows as $row) {
            $parent = $row->getAttribute($parentColumn);
            $key = $parent === null || ! isset($ids[(string) $parent]) ? '' : (string) $parent;
            $byParent[$key][] = [
                'value' => $row->getAttribute($valueColumn),
                'label' => (string) $row->getAttribute($labelColumn),
            ];
        }

        $build = static function (string $parent, array $seen) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent[$parent] ?? [] as $node) {
                $id = (string) $node['value'];
                // A cycle in the data (a row that is its own ancestor) stops here.
                if (! isset($seen[$id])) {
                    $children = $build($id, $seen + [$id => true]);
                    if ($children !== []) {
                        $node['children'] = $children;
                    }
                }
                $nodes[] = $node;
            }

            return $nodes;
        };

        return $build('', []);
    }
}

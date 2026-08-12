<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * A resource's tree page: a hierarchy instead of a table.
 *
 * It applies to the resources whose `viewMode() === 'tree'`, which is detected
 * from the `parent()` and `children()` Eloquent relations of a
 * self-referencing model. The SPA loads the data through the
 * `{resource}.tree` action; see ResourceController::tree.
 */
final class GeneratedTreeScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'tree';
    }

    public function name(): string
    {
        return $this->resource::label();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(mixed ...$params): array
    {
        return [
            'columns' => array_map(
                static fn (TableColumn $c): array => $c->toArray(),
                $this->resource->columns(),
            ),
            'filters' => array_map(
                static fn ($f): array => $f->toArray(),
                $this->resource->filters(),
            ),
            'searchable' => $this->resource->searchableFields(),
            'with' => $this->resource->with(),
            'permissions' => $this->resource->meta()['permissions'] ?? [],
            'features' => $this->resource->meta()['features'] ?? [],
            'view_mode' => 'tree',
            'parent_key' => $this->resource->hierarchyParentKey(),
            'label_column' => $this->resolveLabelColumn(),
        ];
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        return [
            View::make('admin.tree', [
                'resource' => $this->resource::slug(),
            ]),
        ];
    }

    /**
     * @return list<Action>
     */
    public function commandBar(): array
    {
        $userActions = $this->resource->actions();

        $createUrl = '/admin/r/'.$this->resource::slug().'/create';
        $createLink = Link::make((string) __('admin::admin.resource.create_button'))->href($createUrl);
        $createPermission = $this->resource::permission().'.create';
        $createLink->permission($createPermission);

        return [$createLink, ...$userActions];
    }

    /**
     * The column a tree node's label is built from: the first searchable
     * column, falling back to `'name'`.
     */
    private function resolveLabelColumn(): string
    {
        foreach ($this->resource->columns() as $column) {
            $arr = $column->toArray();
            if (! empty($arr['searchable'])) {
                return (string) ($arr['name'] ?? 'name');
            }
        }

        return 'name';
    }
}

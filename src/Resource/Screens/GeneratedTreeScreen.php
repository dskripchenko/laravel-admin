<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Tree-страница ресурса: иерархическое дерево вместо таблицы.
 *
 * Применяется к Resource'ам с `viewMode() === 'tree'` (автодетект по
 * `parent()`/`children()` Eloquent relations на self-referencing модели).
 * Данные подгружаются SPA через `{resource}.tree` action (см.
 * ResourceController::tree).
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
        $createLink = Link::make('Создать')->href($createUrl);
        $createPermission = $this->resource::permission().'.create';
        $createLink->permission($createPermission);

        return [$createLink, ...$userActions];
    }

    /**
     * Имя колонки, по которой формируется лейбл узла дерева.
     * Берётся первая search-колонка, иначе `'name'` как fallback.
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

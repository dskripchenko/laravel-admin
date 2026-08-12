<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * A resource's list page: the table with its columns, the filters and the
 * command bar.
 *
 * The state loads no data itself — once the SPA has the compile() result it
 * calls `{resource}.search` (see ResourceController.search). What is here is
 * the description of the page and its links.
 */
final class GeneratedListScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'list';
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
        ];
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        return [
            View::make('admin.table', [
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

        // "Create" is added by default when the resource has a create permission.
        $createUrl = '/admin/r/'.$this->resource::slug().'/create';
        $createLink = Link::make((string) __('admin::admin.resource.create_button'))->href($createUrl);
        $createPermission = $this->resource::permission().'.create';
        $createLink->permission($createPermission);

        return [$createLink, ...$userActions];
    }
}

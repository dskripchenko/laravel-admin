<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\View;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * List-страница ресурса: таблица с колонками + фильтры + commandBar.
 *
 * State не загружает данные сам — SPA после получения compile() делает
 * запрос на `{resource}.search` (см. ResourceController.search). Здесь
 * только описание страницы и ссылок.
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

        // По умолчанию добавляем «Создать» если у Resource есть create-permission.
        $createUrl = '/admin/resources/'.$this->resource::slug().'/create';
        $createLink = Link::make('Создать')->href($createUrl);
        $createPermission = $this->resource::permission().'.create';
        $createLink->permission($createPermission);

        return [$createLink, ...$userActions];
    }
}

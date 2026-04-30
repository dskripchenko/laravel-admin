<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Layout\Infolist;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only страница записи через Resource::infolist().
 *
 * compile($id) загружает запись и кладёт её в state. Layout — Infolist с
 * entries из Resource::infolist(). Кнопки в commandBar: Edit/Delete/Назад.
 */
final class GeneratedViewScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'view';
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
        $id = $params[0] ?? null;
        if ($id === null) {
            return ['record' => []];
        }

        $record = $this->resource->modelQuery()->find($id);
        if ($record === null) {
            throw new NotFoundHttpException("Record {$id} not found");
        }

        return ['record' => $record->toArray(), 'id' => $record->getKey()];
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        return [
            Infolist::make($this->resource->infolist()),
        ];
    }

    /**
     * @return list<Action>
     */
    public function commandBar(): array
    {
        $base = $this->resource::permission();

        return [
            Link::make('Редактировать')
                ->href('/admin/resources/'.$this->resource::slug().'/{id}/edit')
                ->permission($base.'.update'),
            Link::make('Назад')
                ->href('/admin/resources/'.$this->resource::slug()),
        ];
    }
}

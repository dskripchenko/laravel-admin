<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Форма редактирования существующей записи.
 *
 * `query($id)` загружает запись из Resource::modelQuery() и кладёт в state.
 * Если запись не найдена — кидает 404. Save идёт через ResourceController.update.
 */
final class GeneratedEditScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'edit';
    }

    public function name(): string
    {
        return 'Редактировать: '.$this->resource::label();
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
        $updateFields = array_values(array_filter(
            $this->resource->fields(),
            static fn (Field $f): bool => $f->appliesTo('update'),
        ));

        return [Rows::make($updateFields)];
    }

    /**
     * @return list<Action>
     */
    public function commandBar(): array
    {
        $base = $this->resource::permission();

        return [
            Button::make('Сохранить')
                ->withName('save')
                ->method('update')
                ->permission($base.'.update'),
            Button::make('Удалить')
                ->withName('delete')
                ->method('delete')
                ->permission($base.'.delete')
                ->confirm('Удалить запись?'),
            Link::make('Назад')
                ->href('/admin/resources/'.$this->resource::slug()),
        ];
    }
}

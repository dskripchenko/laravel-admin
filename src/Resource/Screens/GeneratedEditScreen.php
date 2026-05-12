<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Layout\Rows;

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
        return $this->queryRecord($params[0] ?? null);
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        $custom = $this->resource->formLayout('update');
        if ($custom !== []) {
            return [Rows::make($custom)];
        }

        return [Rows::make($this->filterFieldsBy('update'))];
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
            $this->buildBackLink(),
        ];
    }
}

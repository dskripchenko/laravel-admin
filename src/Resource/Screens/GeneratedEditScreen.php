<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Layout\Rows;

/**
 * The form editing an existing record.
 *
 * `query($id)` loads the record through Resource::modelQuery() and puts it
 * into the state; a record that is not there gives a 404. The save goes
 * through ResourceController.update.
 */
final class GeneratedEditScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'edit';
    }

    public function name(): string
    {
        // The record is not loaded yet when the name is asked for: "Edit
        // author" with a singular, "Edit record: Authors" without.
        $singular = $this->resource::localizedSingularLabel();

        return $singular !== null
            ? (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string('Редактирование: :singular', ['singular' => $singular])
            : (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string('Редактирование записи: :label', [
                'label' => (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->resource::label()),
            ]);
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
                // "Delete this author?" in English; the Russian source needs
                // no noun, so it reads right without the accusative.
                ->confirm((string) \Dskripchenko\LaravelAdmin\I18n\Localize::string('Удалить эту запись?', [
                    'singular' => $this->resource::localizedSingularLabel()
                        ?? (string) \Dskripchenko\LaravelAdmin\I18n\Localize::string('запись'),
                ])),
            $this->buildBackLink(),
        ];
    }
}

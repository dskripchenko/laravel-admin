<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Screens;

use Dskripchenko\LaravelAdmin\Action\Action;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\Link;
use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Layout\Rows;

/**
 * Форма создания записи Resource'а.
 *
 * compile() возвращает field-описания и пустой state — фактическое создание
 * идёт через ResourceController.create.
 */
final class GeneratedCreateScreen extends GeneratedScreen
{
    public function kind(): string
    {
        return 'create';
    }

    public function name(): string
    {
        return 'Создать: '.$this->resource::label();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(mixed ...$params): array
    {
        $defaults = [];
        foreach ($this->resource->fields() as $field) {
            if (! $field->appliesTo('create')) {
                continue;
            }
            $defaults[$field->name()] = null;
        }

        return ['record' => $defaults];
    }

    /**
     * @return list<\Dskripchenko\LaravelAdmin\Layout\Layout>
     */
    public function layout(): array
    {
        $createFields = array_values(array_filter(
            $this->resource->fields(),
            static fn (Field $f): bool => $f->appliesTo('create'),
        ));

        return [Rows::make($createFields)];
    }

    /**
     * @return list<Action>
     */
    public function commandBar(): array
    {
        return [
            Button::make('Сохранить')
                ->withName('save')
                ->method('create')
                ->permission($this->resource::permission().'.create'),
            Link::make('Отмена')
                ->href('/admin/resources/'.$this->resource::slug()),
        ];
    }
}

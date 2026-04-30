<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Resource для тестов inline-edit + summary.
 *
 * @internal
 */
final class TestEditableResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
            Input::make('status'),
            Number::make('amount')->integer(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name')->editable(['required', 'string', 'max:255']),
            TableColumn::make('status')->editable(['required', 'string']),
            TableColumn::make('amount')->summary(['sum', 'avg', 'count', 'min', 'max']),
        ];
    }
}

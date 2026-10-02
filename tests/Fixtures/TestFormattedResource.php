<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * A resource whose columns carry server-side formatters.
 *
 * @internal
 */
final class TestFormattedResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
            Input::make('email')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name')->format(static fn (mixed $value): string => mb_strtoupper((string) $value)),
            TableColumn::make('email')->format(static fn (mixed $value, array $row): string => $row['name'].' <'.$value.'>'),
        ];
    }
}

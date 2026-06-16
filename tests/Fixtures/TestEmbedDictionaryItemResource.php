<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * @internal
 */
final class TestEmbedDictionaryItemResource extends Resource
{
    public static string $model = TestEmbedDictionaryItemModel::class;

    public function fields(): array
    {
        return [
            Input::make('label')->required(),
            Number::make('sort_order')->step(1),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('label')->search()->editable(['required', 'string']),
            TableColumn::make('sort_order')->editable(['integer'], as: 'number'),
        ];
    }
}

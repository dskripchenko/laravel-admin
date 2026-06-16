<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Resource для tests иерархического дерева.
 *
 * @internal
 */
final class TestTreeNodeResource extends Resource
{
    public static string $model = TestTreeNodeModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')->sort()->search(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
final class TestReorderableUserModel extends Model
{
    protected $table = 'reorderable_users';

    protected $guarded = [];
}

/**
 * @internal
 */
final class TestReorderableResource extends Resource
{
    public static string $model = TestReorderableUserModel::class;

    public function reorderable(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [Input::make('name')->required()];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name'),
            TableColumn::make('position'),
        ];
    }
}

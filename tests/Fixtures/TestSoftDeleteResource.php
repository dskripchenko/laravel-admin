<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @internal
 */
final class TestSoftDeleteUserModel extends Model
{
    use SoftDeletes;

    protected $table = 'soft_users';

    protected $guarded = [];
}

/**
 * @internal
 */
final class TestSoftDeleteResource extends Resource
{
    public static string $model = TestSoftDeleteUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name'),
        ];
    }
}

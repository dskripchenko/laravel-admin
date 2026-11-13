<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * A resource whose columns() can only run once the host's routes exist
 * (it builds a URL with route()): compiling the panel's API must not call it.
 */
final class ColumnsNeedRoutesResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'columns-need-routes';
    }

    public static function permission(): string
    {
        return 'admin.columns-need-routes';
    }

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function columns(): array
    {
        throw new RuntimeException('columns() was called while the routes were being built');
    }
}

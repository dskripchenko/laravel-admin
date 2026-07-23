<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Resource с searchable-колонками для тестов глобального поиска (GlobalSearch).
 *
 * @internal
 */
final class TestSearchableUserResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static string $icon = 'users';

    public static function slug(): string
    {
        return 'search-users';
    }

    public static function permission(): string
    {
        return 'admin.search.users';
    }

    public static function label(): string
    {
        return 'Пользователи';
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
            Input::make('email'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('name')->search(),
            TableColumn::make('email')->search(),
            TableColumn::make('status'),
        ];
    }
}

<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource с per-row editable override — для проверки _editable инъекции.
 * Row, имя которой содержит '(locked)', помечается не-editable на колонку `name`.
 *
 * @internal
 */
final class TestPerRowEditableResource extends Resource
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
            TableColumn::make('name')->editable(['required', 'string']),
            TableColumn::make('email'),
        ];
    }

    public function editableForRow(Model $row, string $column): bool
    {
        if ($column === 'name' && str_contains((string) $row->getAttribute('name'), '(locked)')) {
            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Resources;

use App\Models\Category;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Slug;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class CategoryResource extends Resource
{
    public static string $model = Category::class;

    public static string $icon = 'folder';

    public function reorderable(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Название'),
            Slug::make('slug')->from('name')->title('URL slug'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')
                ->sort()
                ->search()
                ->editable(['required', 'string', 'max:255']),
            TableColumn::make('slug')->copyable(),
            TableColumn::make('position')->sort(),
        ];
    }
}

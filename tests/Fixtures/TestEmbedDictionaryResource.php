<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\ResourceTable;
use Dskripchenko\LaravelAdmin\Layout\Tabs;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * @internal
 */
final class TestEmbedDictionaryResource extends Resource
{
    public static string $model = TestEmbedDictionaryModel::class;

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
            TableColumn::make('name')->search(),
        ];
    }

    public function formLayout(string $context): array
    {
        if ($context !== 'update') {
            return [];
        }

        return [
            Tabs::make([
                'Основные' => $this->fields(),
                'Элементы' => [
                    ResourceTable::for(TestEmbedDictionaryItemResource::class)
                        ->foreignKey('dictionary_id')
                        ->hideColumns(['dictionary_id'])
                        ->features([
                            'create' => true,
                            'delete' => true,
                            'bulkDelete' => true,
                        ]),
                ],
            ]),
        ];
    }
}

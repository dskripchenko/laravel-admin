<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Resource\Resource;
use InvalidArgumentException;

/**
 * Встроенная таблица другого Resource'а, отфильтрованная по FK родителя.
 *
 * Применение — внутри formLayout('update') иерархического Resource'а
 * для отображения «детей» на собственной edit-странице:
 *
 *   Tabs::make([
 *       'Основные' => $this->fields(),
 *       'Элементы' => [
 *           ResourceTable::for(DictionaryItemResource::class)
 *               ->foreignKey('dictionary_id')
 *               ->hideColumns(['dictionary_id'])
 *               ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
 *       ],
 *   ])
 *
 * Фронт (registered as layout `'admin.resource-table'`) на mount резолвит
 * текущую parent-запись из `useResourceFormStore`, подставляет
 * `{[foreign_key]: parent[parent_field]}` как initial filter и грузит
 * список через стандартный `POST /{resource}/search`.
 */
final class ResourceTable extends Layout
{
    /** @var class-string<resource> */
    private string $resourceClass;

    private string $foreignKey;

    private string $parentField = 'id';

    /** @var list<string> */
    private array $hideColumns = [];

    /** @var array{create: bool, delete: bool, bulkDelete: bool} */
    private array $features = [
        'create' => false,
        'delete' => false,
        'bulkDelete' => false,
    ];

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public static function for(string $resourceClass): self
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            throw new InvalidArgumentException(
                'ResourceTable::for() expects subclass of '.Resource::class.", got {$resourceClass}",
            );
        }
        $instance = new self;
        $instance->resourceClass = $resourceClass;
        // Sensible default FK по имени родительского Resource — host обычно override'ит.
        $instance->foreignKey = $resourceClass::slug();

        return $instance;
    }

    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    /**
     * Имя колонки в parent-record, чьё значение подставляется в filter.
     * Default 'id'.
     */
    public function parentField(string $column): self
    {
        $this->parentField = $column;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function hideColumns(array $columns): self
    {
        $this->hideColumns = $columns;

        return $this;
    }

    /**
     * @param  array<string, bool>  $features
     */
    public function features(array $features): self
    {
        $this->features = array_merge($this->features, $features);

        return $this;
    }

    public function type(): string
    {
        return 'admin.resource-table';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->props = [
            'resource' => $this->resourceClass::slug(),
            'foreign_key' => $this->foreignKey,
            'parent_field' => $this->parentField,
            'hide_columns' => $this->hideColumns,
            'features' => $this->features,
        ];

        return parent::toArray();
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Resource\Resource;
use InvalidArgumentException;

/**
 * An embedded table of another resource, filtered by the parent's foreign key.
 *
 * It belongs inside the formLayout('update') of a hierarchical resource, to
 * show the children on the parent's own edit page:
 *
 *   Tabs::make([
 *       'General' => $this->fields(),
 *       'Items' => [
 *           ResourceTable::for(DictionaryItemResource::class)
 *               ->foreignKey('dictionary_id')
 *               ->hideColumns(['dictionary_id'])
 *               ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
 *       ],
 *   ])
 *
 * On the frontend — registered as the layout `'admin.resource-table'` — it
 * resolves the current parent record from `useResourceFormStore` on mount,
 * puts `{[foreign_key]: parent[parent_field]}` in as the initial filter and
 * loads the list through the usual `POST /{resource}/search`.
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
        // A sensible default foreign key from the parent resource's name; a host usually overrides it.
        $instance->foreignKey = $resourceClass::slug();

        return $instance;
    }

    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    /**
     * The column of the parent record whose value goes into the filter; 'id'
     * by default.
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

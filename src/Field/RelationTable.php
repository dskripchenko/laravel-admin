<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * An inline table of related records, over a HasMany or a BelongsToMany.
 *
 * The SPA renders it as a table with add and remove buttons. CRUD over the
 * related records does not go through this field: reading and writing are
 * serialized through the relation on the parent model — Resource::with() plus
 * the sync logic.
 *
 * @method $this addable(bool $addable = true)
 * @method $this removable(bool $removable = true)
 */
final class RelationTable extends Field
{
    public function fieldType(): string
    {
        return 'relation_table';
    }

    /**
     * The relation's name on the parent model — 'comments' for User->comments(), say.
     */
    public function relation(string $relationName): static
    {
        $this->attributes['relation'] = $relationName;

        return $this;
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->attributes['relatedModel'] = $model;

        return $this;
    }

    /**
     * @param  list<TableColumn>  $columns
     */
    public function columns(array $columns): static
    {
        $this->attributes['columns'] = array_map(
            static fn (TableColumn $c): array => $c->toArray(),
            $columns,
        );

        return $this;
    }

    /**
     * The relations to eager-load.
     *
     * @param  list<string>  $relations
     */
    public function with(array $relations): static
    {
        $this->attributes['with'] = $relations;

        return $this;
    }

    /**
     * The pivot fields of a BelongsToMany, shown as extra columns.
     *
     * @param  list<string>  $fields
     */
    public function withPivot(array $fields): static
    {
        $this->attributes['withPivot'] = $fields;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Inline-таблица связанных записей (HasMany / BelongsToMany).
 *
 * SPA рендерит как таблицу с кнопками add/remove. Сам CRUD над связанными
 * записями не делается через этот Field — сериализация для read/write идёт
 * через relation на родительской модели (Resource::with() + sync-логика).
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
     * Имя relation на родительской модели (например, 'comments' для User->comments()).
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
     * Eager-loading relations при подгрузке.
     *
     * @param  list<string>  $relations
     */
    public function with(array $relations): static
    {
        $this->attributes['with'] = $relations;

        return $this;
    }

    /**
     * Pivot-поля для BelongsToMany (отображаются как доп.колонки).
     *
     * @param  list<string>  $fields
     */
    public function withPivot(array $fields): static
    {
        $this->attributes['withPivot'] = $fields;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Concerns\HasOptions;
use Illuminate\Database\Eloquent\Model;

/**
 * The selector of a BelongsTo or BelongsToMany relation.
 *
 * Underneath it is an ordinary select, bound to an Eloquent model:
 *  - relatedModel — the class-string<Model> whose records become the options;
 *  - displayColumn — the column behind the label;
 *  - valueColumn — the column behind the value; 'id' by default;
 *  - searchColumns — the columns the server-side search (?q=...) looks in;
 *  - preload — the relations to eager-load while fetching the options.
 *
 * The SPA sends ?q=...&page=... to the resource's endpoint; a dedicated
 * `options` controller action is left to whoever needs one.
 */
final class RelationSelect extends Field
{
    use HasOptions;

    public function fieldType(): string
    {
        return 'relation_select';
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function relation(string $model, string $displayColumn = 'name', string $valueColumn = 'id'): static
    {
        $this->attributes['relatedModel'] = $model;
        $this->attributes['displayColumn'] = $displayColumn;
        $this->attributes['valueColumn'] = $valueColumn;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function searchable(array $columns): static
    {
        $this->attributes['searchColumns'] = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $relations
     */
    public function preload(array $relations): static
    {
        $this->attributes['preload'] = $relations;

        return $this;
    }

    /**
     * The options are required for the rendering — the SPA's component is a
     * plain select, with no asynchronous search — so when the host set none
     * explicitly, through options() or eager(), we load them from the related
     * model at serialization time.
     */
    public function toArray(): array
    {
        if (($this->attributes['options'] ?? []) === []) {
            $this->eager();
        }

        return parent::toArray();
    }

    /**
     * Loads the options right away, which suits small data sets such as
     * reference tables. Use it with care: for a large table, let the SPA fetch
     * the options instead.
     */
    public function eager(int $limit = 100): static
    {
        $model = $this->getAttribute('relatedModel');
        if (! is_string($model) || ! class_exists($model)) {
            return $this;
        }

        $valueColumn = (string) ($this->getAttribute('valueColumn') ?? 'id');
        $displayColumn = (string) ($this->getAttribute('displayColumn') ?? 'name');
        $preload = (array) ($this->getAttribute('preload') ?? []);

        /** @var class-string<Model> $model */
        $query = $model::query();
        if ($preload !== []) {
            $query->with($preload);
        }

        $records = $query->limit($limit)->get([$valueColumn, $displayColumn]);
        $items = $records->map(static fn (Model $m): array => [
            'value' => $m->getAttribute($valueColumn),
            'label' => (string) $m->getAttribute($displayColumn),
        ])->all();

        $this->attributes['options'] = $items;

        return $this;
    }
}

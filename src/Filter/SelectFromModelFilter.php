<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Select-фильтр с options из Eloquent-модели.
 *
 * `SelectFromModelFilter::for('user_id')->fromModel(User::class, 'name')`
 */
final class SelectFromModelFilter extends Filter
{
    /** @var class-string<Model>|null */
    private ?string $modelClass = null;

    private string $valueColumn = 'id';

    private string $labelColumn = 'name';

    private int $limit = 200;

    public function type(): string
    {
        return 'select_from_model';
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function fromModel(string $model, string $labelColumn = 'name', string $valueColumn = 'id'): static
    {
        $this->modelClass = $model;
        $this->labelColumn = $labelColumn;
        $this->valueColumn = $valueColumn;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function apply(EloquentBuilder $query, mixed $value): EloquentBuilder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->multiple && is_array($value)) {
            return $query->whereIn($this->field, array_values($value));
        }

        return $query->where($this->field, '=', $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = parent::toArray();
        $base['options'] = $this->loadOptions();

        return $base;
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    private function loadOptions(): array
    {
        if ($this->modelClass === null) {
            return [];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        $records = $modelClass::query()
            ->limit($this->limit)
            ->get([$this->valueColumn, $this->labelColumn]);

        return $records->map(fn (Model $m): array => [
            'value' => $m->getAttribute($this->valueColumn),
            'label' => (string) $m->getAttribute($this->labelColumn),
        ])->all();
    }
}

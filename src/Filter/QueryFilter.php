<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Произвольный callable-фильтр.
 *
 * `QueryFilter::for('legacy_status')->using(fn ($q, $value) => $q->where(...))`.
 *
 * Удобен для нестандартных полей: фильтрация через relation, raw SQL,
 * complex conditions. UI-тип задаётся отдельно через `as($uiType)`.
 */
final class QueryFilter extends Filter
{
    /** @var (callable(Builder, mixed): Builder)|null */
    private $callback = null;

    private string $uiType = 'input';

    public function type(): string
    {
        return $this->uiType;
    }

    /**
     * Тип UI-control'а: 'input' | 'switcher' | 'date_range' | 'options' | 'custom'.
     */
    public function as(string $uiType): static
    {
        $this->uiType = $uiType;

        return $this;
    }

    /**
     * @param  callable(Builder, mixed): Builder  $callback
     */
    public function using(callable $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        if ($this->callback === null) {
            return $query;
        }

        return ($this->callback)($query, $value);
    }
}

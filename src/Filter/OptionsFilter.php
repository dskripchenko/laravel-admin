<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Select-фильтр со статичным списком options.
 *
 * `OptionsFilter::for('status')->options(['active' => 'Active', 'banned' => 'Banned'])`
 *
 * Поддерживает `multiple()` — тогда применяет `WHERE col IN (...)`.
 */
final class OptionsFilter extends Filter
{
    /** @var array<int|string, string> */
    private array $options = [];

    public function type(): string
    {
        return 'options';
    }

    /**
     * @param  array<int|string, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
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
        $base['options'] = array_map(
            static fn ($value, $key): array => ['value' => $key, 'label' => $value],
            $this->options,
            array_keys($this->options),
        );

        return $base;
    }
}

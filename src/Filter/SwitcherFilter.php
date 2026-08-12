<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * A boolean filter for a tri-state UI: `null` for any, `true`, `false`.
 *
 * `Filter\Switcher::for('is_active')` has three states:
 *   - `null` or absent → no filtering at all.
 *   - `true` / `1` / `'true'` → `WHERE is_active = 1`.
 *   - `false` / `0` / `'false'` → `WHERE is_active = 0`.
 */
final class SwitcherFilter extends Filter
{
    public function type(): string
    {
        return 'switcher';
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            return $query;
        }

        return $query->where($this->field, $bool);
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * LIKE-поиск по текстовому полю.
 *
 * `Filter\Input::for('email')` — `?filters[email]=ivan` → `WHERE email LIKE '%ivan%'`.
 */
final class InputFilter extends Filter
{
    public function type(): string
    {
        return 'input';
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where($this->field, 'like', '%'.((string) $value).'%');
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Range-фильтр по дате (between).
 *
 * Принимает `{from, to}` строки в формате `Y-m-d` (или ISO). NULL `from`/`to`
 * означает «без нижней/верхней границы».
 *
 * URL: `?filters[created_at][from]=2024-01-01&filters[created_at][to]=2024-12-31`
 */
final class DateRangeFilter extends Filter
{
    public function type(): string
    {
        return 'date_range';
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if (is_string($from) && $from !== '') {
            $query = $query->where($this->field, '>=', $from);
        }
        if (is_string($to) && $to !== '') {
            $query = $query->where($this->field, '<=', $to);
        }

        return $query;
    }
}

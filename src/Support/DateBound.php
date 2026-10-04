<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Applies the upper bound of a date range so that the bound's day is
 * included in full.
 *
 * A bare `Y-m-d` upper bound compared with `<=` against a datetime column
 * stops at midnight and misses the whole last day: `'2026-05-08 14:00' <=
 * '2026-05-08'` is false. A date-only bound therefore becomes `< next day`;
 * a bound that carries a time is compared as it is, with `<=`.
 */
final class DateBound
{
    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function applyUpper(Builder $query, string $column, string $to): Builder
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            try {
                $next = Carbon::createFromFormat('!Y-m-d', $to)?->addDay()->format('Y-m-d');
            } catch (\Throwable) {
                $next = null;
            }
            if (is_string($next)) {
                $query->where($column, '<', $next);

                return $query;
            }
        }

        $query->where($column, '<=', $to);

        return $query;
    }
}

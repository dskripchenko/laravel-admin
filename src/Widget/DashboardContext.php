<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * What a widget knows about the dashboard it is being computed for — today,
 * the period picked in the dashboard's switcher.
 *
 * A widget reads it through `$this->dashboardContext()` inside `data()`:
 *
 *     public function data(): array
 *     {
 *         $orders = $this->dashboardContext()
 *             ->constrain(Order::query(), 'created_at')
 *             ->count();
 *
 *         return ['stats' => [['label' => 'Orders', 'value' => $orders]]];
 *     }
 *
 * A period is `all` or a number of days followed by `d`: `7d`, `30d`, `90d`.
 */
final class DashboardContext
{
    /** The periods a dashboard offers when it does not list its own. */
    public const DEFAULT_PERIODS = ['7d', '30d', '90d', 'all'];

    public const DEFAULT_PERIOD = '30d';

    public readonly string $period;

    public function __construct(string $period = self::DEFAULT_PERIOD, private readonly ?CarbonImmutable $now = null)
    {
        $this->period = self::isValidPeriod($period) ? $period : self::DEFAULT_PERIOD;
    }

    public static function isValidPeriod(string $period): bool
    {
        return $period === 'all' || preg_match('/^[1-9]\d{0,3}d$/', $period) === 1;
    }

    /**
     * The length of the period in days; null for `all`.
     */
    public function days(): ?int
    {
        if ($this->period === 'all') {
            return null;
        }

        return (int) substr($this->period, 0, -1);
    }

    public function isAll(): bool
    {
        return $this->period === 'all';
    }

    /**
     * The start of the window; null for `all`, which has none.
     */
    public function from(): ?CarbonImmutable
    {
        $days = $this->days();

        return $days === null ? null : $this->to()->subDays($days);
    }

    /**
     * The end of the window — the moment the data is computed.
     */
    public function to(): CarbonImmutable
    {
        return $this->now ?? CarbonImmutable::now();
    }

    /**
     * Limits a query to the period by a timestamp column. A query for `all`
     * is returned untouched.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function constrain(Builder $query, string $column = 'created_at'): Builder
    {
        $from = $this->from();
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        return $query;
    }
}

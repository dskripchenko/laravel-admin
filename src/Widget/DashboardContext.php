<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * What a widget knows about the dashboard it is being computed for — today,
 * the period picked in the dashboard's switcher, and whether the user asked to
 * compare it with the period before.
 *
 * A widget reads it through `$this->dashboardContext()` inside `data()`:
 *
 *     public function data(): array
 *     {
 *         $ctx = $this->dashboardContext();
 *         $orders = $ctx->constrain(Order::query(), 'created_at')->count();
 *         $before = $ctx->comparing()?->constrain(Order::query(), 'created_at')->count();
 *
 *         return StatsOverviewWidget::make()
 *             ->stat('Orders', $orders, url: $ctx->listUrl('/r/orders', 'created_at'))
 *             ->data();
 *     }
 *
 * The periods:
 *   - `all` — no bounds;
 *   - `Nd` — the last N days up to now: `7d`, `30d`, `90d`;
 *   - `today` — since midnight;
 *   - `month`, `quarter` — since the start of the current month or quarter;
 *   - `Y-m-d..Y-m-d` — a range of whole days, both ends included.
 */
final class DashboardContext
{
    /** The periods a dashboard offers when it does not list its own. */
    public const DEFAULT_PERIODS = ['7d', '30d', '90d', 'all'];

    public const DEFAULT_PERIOD = '30d';

    /** The named periods besides `Nd`. */
    public const NAMED_PERIODS = ['all', 'today', 'month', 'quarter'];

    private const RANGE = '/^(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/';

    public readonly string $period;

    private ?CarbonImmutable $rangeFrom = null;

    private ?CarbonImmutable $rangeTo = null;

    public function __construct(
        string $period = self::DEFAULT_PERIOD,
        private readonly ?CarbonImmutable $now = null,
        private readonly bool $compare = false,
    ) {
        $this->period = self::isValidPeriod($period) ? $period : self::DEFAULT_PERIOD;
        if (preg_match(self::RANGE, $this->period, $m) === 1) {
            $this->rangeFrom = CarbonImmutable::parse($m[1])->startOfDay();
            $this->rangeTo = CarbonImmutable::parse($m[2])->endOfDay();
        }
    }

    /**
     * A context over an explicit window — what previous() returns.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $context = new self($from->format('Y-m-d').'..'.$to->format('Y-m-d'));
        $context->rangeFrom = $from;
        $context->rangeTo = $to;

        return $context;
    }

    public static function isValidPeriod(string $period): bool
    {
        if (in_array($period, self::NAMED_PERIODS, true) || preg_match('/^[1-9]\d{0,3}d$/', $period) === 1) {
            return true;
        }
        if (preg_match(self::RANGE, $period, $m) !== 1) {
            return false;
        }
        foreach ([$m[1], $m[2]] as $date) {
            [$y, $mo, $d] = array_map('intval', explode('-', $date));
            if (! checkdate($mo, $d, $y)) {
                return false;
            }
        }

        return $m[1] <= $m[2];
    }

    /** A range of whole days, `Y-m-d..Y-m-d`, rather than a named period. */
    public static function isRange(string $period): bool
    {
        return preg_match(self::RANGE, $period) === 1;
    }

    /**
     * The period's caption for the switcher: «За 7 дней», «Сегодня»,
     * «01.01.2026 – 31.03.2026».
     */
    public static function labelFor(string $period): string
    {
        if (preg_match(self::RANGE, $period, $m) === 1) {
            return CarbonImmutable::parse($m[1])->format('d.m.Y').' – '.CarbonImmutable::parse($m[2])->format('d.m.Y');
        }
        if (preg_match('/^(\d+)d$/', $period, $m) === 1) {
            return (string) __('За :count дней', ['count' => $m[1]]);
        }

        return (string) match ($period) {
            'today' => __('Сегодня'),
            'month' => __('Этот месяц'),
            'quarter' => __('Этот квартал'),
            'all' => __('Всё время'),
            default => $period,
        };
    }

    public function label(): string
    {
        return self::labelFor($this->period);
    }

    /**
     * The length of the period in days, rounded up; null for `all`.
     */
    public function days(): ?int
    {
        if ($this->period === 'all') {
            return null;
        }
        if (preg_match('/^(\d+)d$/', $this->period, $m) === 1) {
            return (int) $m[1];
        }
        $from = $this->from();
        if ($from === null) {
            return null;
        }

        return max(1, (int) ceil($from->diffInSeconds($this->to(), true) / 86400));
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
        if ($this->rangeFrom !== null) {
            return $this->rangeFrom;
        }
        $now = $this->now();

        return match (true) {
            $this->period === 'all' => null,
            $this->period === 'today' => $now->startOfDay(),
            $this->period === 'month' => $now->startOfMonth(),
            $this->period === 'quarter' => $now->startOfQuarter(),
            default => $now->subDays((int) substr($this->period, 0, -1)),
        };
    }

    /**
     * The end of the window: the last moment of a range, else the moment the
     * data is computed.
     */
    public function to(): CarbonImmutable
    {
        return $this->rangeTo ?? $this->now();
    }

    /**
     * Whether the user asked to compare the period with the one before it.
     */
    public function compare(): bool
    {
        return $this->compare;
    }

    /**
     * The window of the same length right before this one — the 7 days before
     * the last 7, the previous quarter's same span; null for `all`.
     */
    public function previous(): ?self
    {
        $from = $this->from();
        if ($from === null) {
            return null;
        }
        $to = $this->to();
        $length = $from->diffInSeconds($to, true);
        if (in_array($this->period, ['month', 'quarter'], true)) {
            // The same span of the previous month or quarter: 1–13 March for 1–13 April.
            $start = $this->period === 'month' ? $from->subMonthNoOverflow() : $from->subMonthsNoOverflow(3);

            return self::between($start, $start->addSeconds((int) $length));
        }

        return self::between($from->subSeconds((int) $length + 1), $from->subSecond());
    }

    /**
     * previous() when the user asked to compare, null otherwise — what a
     * widget reads to decide whether to compute the second number.
     */
    public function comparing(): ?self
    {
        return $this->compare ? $this->previous() : null;
    }

    /**
     * Limits a query to the period by a timestamp column. A query for `all`
     * is returned untouched; a range is bounded on both sides.
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
        if ($this->rangeTo !== null) {
            $query->where($column, '<=', $this->rangeTo);
        }

        return $query;
    }

    /**
     * The period as a list's date filter in the address — what a widget's
     * link carries so the list opens on the same period:
     * `['filter[created_at][from]' => '2026-10-14', 'filter[created_at][to]' => '2026-11-13']`.
     * Empty for `all`. `$prefix` — the list's queryKey on a screen
     * (`orders.`).
     *
     * @return array<string, string>
     */
    public function filterQuery(string $field, string $prefix = ''): array
    {
        $from = $this->from();
        if ($from === null) {
            return [];
        }

        return [
            $prefix.'filter['.$field.'][from]' => $from->format('Y-m-d'),
            $prefix.'filter['.$field.'][to]' => $this->to()->format('Y-m-d'),
        ];
    }

    /**
     * A panel path with the period as a date filter: `listUrl('/r/orders',
     * 'created_at')` → `/r/orders?filter[created_at][from]=…&filter[created_at][to]=…`.
     * The list must declare a DateRangeFilter on that field.
     *
     * @param  array<string, scalar>  $extra  More query parameters
     */
    public function listUrl(string $path, string $field, array $extra = [], string $prefix = ''): string
    {
        $query = [...$extra, ...$this->filterQuery($field, $prefix)];
        if ($query === []) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function now(): CarbonImmutable
    {
        return $this->now ?? CarbonImmutable::now();
    }
}

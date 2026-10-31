<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Views;

use DateTimeInterface;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reads a resource's records as calendar events and turns a moved event back
 * into column values.
 *
 * An event carries `start` and `end` as the panel shows them: 'Y-m-d' for an
 * all-day event, 'Y-m-d H:i' for a timed one. The end of an all-day event is
 * its last day, inclusive.
 */
final class CalendarFeed
{
    /**
     * At most this many events per request: a calendar range is a few weeks,
     * a year view is the most there is.
     */
    public const MAX_EVENTS = 1000;

    public function __construct(
        private readonly Resource $resource,
        private readonly CalendarView $view,
    ) {}

    /**
     * The records whose interval intersects the days from `$from` to `$to`,
     * both inclusive ('Y-m-d').
     *
     * The bounds are compared as date strings ('Y-m-d'), which date and
     * datetime columns alike compare correctly against on every driver: a
     * record starts before the day after `$to` and ends (or, without an end,
     * starts) on or after `$from`.
     *
     * @param  Builder<Model>  $base
     * @return list<array<string, mixed>>
     */
    public function events(Builder $base, string $from, string $to): array
    {
        $start = $this->view->startColumn();
        $end = $this->view->endColumn();
        $after = Carbon::parse($to)->addDay()->format('Y-m-d');

        $query = (clone $base)
            ->whereNotNull($start)
            ->where($start, '<', $after);
        if ($end === null) {
            $query->where($start, '>=', $from);
        } else {
            $query->where(static function (Builder $q) use ($start, $end, $from): void {
                $q->where($end, '>=', $from)
                    ->orWhere(static function (Builder $q) use ($start, $end, $from): void {
                        $q->whereNull($end)->where($start, '>=', $from);
                    });
            });
        }

        return $query
            ->with($this->view->relations())
            ->reorder()
            ->orderBy($start)
            ->orderBy($query->getModel()->getQualifiedKeyName())
            ->limit(self::MAX_EVENTS)
            ->get()
            ->map(fn (Model $row): ?array => $this->event($row))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A record as an event, or null when its start is empty or unreadable.
     *
     * @return array<string, mixed>|null
     */
    public function event(Model $row): ?array
    {
        $startColumn = $this->view->startColumn();
        $endColumn = $this->view->endColumn();
        $start = self::date($row->getAttribute($startColumn));
        if ($start === null) {
            return null;
        }
        $end = $endColumn !== null ? self::date($row->getAttribute($endColumn)) : null;
        if ($end !== null && $end->lt($start)) {
            $end = null;
        }

        $allDay = $this->isAllDay($row);
        $format = $allDay ? 'Y-m-d' : 'Y-m-d H:i';
        $titlePath = $this->view->titlePath();
        $title = $titlePath !== null ? data_get($row, $titlePath) : null;

        $editable = $this->resource->editableForRow($row, $startColumn)
            && ($endColumn === null || $this->resource->editableForRow($row, $endColumn));

        return [
            'id' => $row->getKey(),
            'title' => is_scalar($title) && (string) $title !== '' ? (string) $title : $this->resource->recordTitle($row),
            'start' => $start->format($format),
            'end' => $end?->format($format),
            'all_day' => $allDay,
            'color' => $this->view->colorOf($row),
            'editable' => $editable,
        ];
    }

    /**
     * Whether the record is an all-day event: the all-day column says so, or
     * the start column holds dates (by its cast or its stored value).
     */
    public function isAllDay(Model $row): bool
    {
        $flag = $this->view->allDayColumn();
        if ($flag !== null) {
            return (bool) $row->getAttribute($flag);
        }

        return $this->isDateOnly($row);
    }

    /**
     * Whether the start column holds dates without a time.
     */
    public function isDateOnly(Model $row): bool
    {
        $column = $this->view->startColumn();
        if (CalendarView::isDateColumn($row, $column)) {
            return true;
        }
        $raw = $row->getRawOriginal($column) ?? $row->getAttributes()[$column] ?? null;

        return is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1;
    }

    /**
     * The column values a move writes: `$start` and `$end` as the panel sends
     * them ('Y-m-d' or 'Y-m-d H:i[:s]'), already validated.
     *
     * A date column gets 'Y-m-d'. A datetime column gets the moment; an
     * all-day value ('Y-m-d') is the start of that day, or for the end the
     * end of that day, as the end of an all-day event is its last day.
     *
     * @return array<string, mixed>
     */
    public function values(Model $row, string $start, ?string $end): array
    {
        $values = [$this->view->startColumn() => $this->columnValue($row, $this->view->startColumn(), $start, false)];
        $endColumn = $this->view->endColumn();
        if ($endColumn !== null) {
            $values[$endColumn] = $end === null ? null : $this->columnValue($row, $endColumn, $end, true);
        }

        return $values;
    }

    private function columnValue(Model $row, string $column, string $value, bool $isEnd): string
    {
        $date = Carbon::parse($value);
        $dayOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        if (CalendarView::isDateColumn($row, $column) || ($dayOnly && $this->isDateOnly($row))) {
            return $date->format('Y-m-d');
        }
        if ($dayOnly) {
            $date = $isEnd ? $date->endOfDay() : $date->startOfDay();
        }

        return $date->format('Y-m-d H:i:s');
    }

    private static function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

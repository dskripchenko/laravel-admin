<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * An event calendar written as markup — events, all-day and timed, with
 * repeats — drawn by the kit's UidEventCalendar: day, week, month, year and
 * agenda views. Read-only unless editable(): then events are dragged,
 * resized, added and changed, and each change is sent to a screen method as
 * the new markup. See MarkupLayout.
 *
 *     Layout::calendar('schedule')                // $state['schedule']
 *         ->initialView('week')
 *         ->date('2026-11-02')
 *         ->views(['week', 'month', 'agenda'])
 *         ->editable('saveSchedule', permission: 'schedule.edit')
 */
final class Calendar extends MarkupLayout
{
    public const VIEWS = ['day', 'week', 'month', 'year', 'agenda'];

    public function type(): string
    {
        return 'calendar';
    }

    /**
     * The view shown first; the markup's `view:` when not set, else the month.
     * (Not `view()`: that name is the Layout::view() factory.)
     */
    public function initialView(string $view): self
    {
        $this->props['view'] = self::checkView($view);

        return $this;
    }

    /** The date shown first, `YYYY-MM-DD`; the markup's `date:` when not set, else today. */
    public function date(string $date): self
    {
        $this->props['date'] = self::checkDate($date, 'Layout::calendar()->date()');

        return $this;
    }

    /**
     * The views offered, in this order.
     *
     * @param  array<array-key, string>  $views
     */
    public function views(array $views): self
    {
        $this->props['views'] = self::checkViews($views, 'Layout::calendar()->views()');

        return $this;
    }

    /**
     * One of VIEWS, or an InvalidArgumentException. Shared with
     * Field\Calendar.
     */
    public static function checkView(string $view): string
    {
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException("Unknown calendar view `{$view}`: ".implode(', ', self::VIEWS));
        }

        return $view;
    }

    /**
     * A non-empty list of VIEWS, or an InvalidArgumentException naming
     * $where. Shared with Field\Calendar.
     *
     * @param  array<array-key, string>  $views
     * @return list<string>
     */
    public static function checkViews(array $views, string $where): array
    {
        if ($views === []) {
            throw new InvalidArgumentException("{$where} needs at least one view");
        }

        return array_values(array_map(self::checkView(...), $views));
    }

    /**
     * A real date written `YYYY-MM-DD`, or an InvalidArgumentException naming
     * $where. Shared with Field\Calendar.
     */
    public static function checkDate(string $date, string $where): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$where} takes YYYY-MM-DD, `{$date}` given");
        }

        return $date;
    }
}

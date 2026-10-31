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
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Layout::calendar()->date() takes YYYY-MM-DD, `{$date}` given");
        }
        $this->props['date'] = $date;

        return $this;
    }

    /**
     * The views offered, in this order.
     *
     * @param  array<array-key, string>  $views
     */
    public function views(array $views): self
    {
        if ($views === []) {
            throw new InvalidArgumentException('Layout::calendar()->views() needs at least one view');
        }
        $this->props['views'] = array_values(array_map(self::checkView(...), $views));

        return $this;
    }

    private static function checkView(string $view): string
    {
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException("Unknown calendar view `{$view}`: ".implode(', ', self::VIEWS));
        }

        return $view;
    }
}

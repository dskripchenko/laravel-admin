<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Layout\Calendar as CalendarLayout;

/**
 * An event calendar written as markup — events, all-day and timed, with
 * repeats — edited in the form with the kit's UidEventCalendar: events are
 * dragged, resized, added and changed, and each change goes into the form's
 * value. The value is the markup, a string in a text column. See MarkupField.
 *
 *     Calendar::make('schedule')
 *         ->initialView('week')
 *         ->date('2026-11-02')
 *         ->views(['week', 'month', 'agenda'])
 */
final class Calendar extends MarkupField
{
    public function fieldType(): string
    {
        return 'calendar';
    }

    /** The view shown first: day, week, month, year or agenda; the markup's `view:` when not set. */
    public function initialView(string $view): static
    {
        $this->attributes['view'] = CalendarLayout::checkView($view);

        return $this;
    }

    /** The date shown first, `YYYY-MM-DD`; the markup's `date:` when not set, else today. */
    public function date(string $date): static
    {
        $this->attributes['date'] = CalendarLayout::checkDate($date, 'Field\Calendar->date()');

        return $this;
    }

    /**
     * The views offered, in this order.
     *
     * @param  array<array-key, string>  $views
     */
    public function views(array $views): static
    {
        $this->attributes['views'] = CalendarLayout::checkViews($views, 'Field\Calendar->views()');

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Infolist;

use Dskripchenko\LaravelAdmin\Layout\Calendar as CalendarLayout;

/**
 * A stored event calendar — the markup of the kit's UidEventCalendar — drawn
 * read-only: day, week, month, year and agenda views.
 *
 *     CalendarEntry::make('schedule')->initialView('agenda')
 *
 * A Field\Calendar on a resource's form is shown this way on its view page
 * by itself.
 */
final class CalendarEntry extends DocumentEntry
{
    public function entryType(): string
    {
        return 'calendar';
    }

    /** The view shown first: day, week, month, year or agenda. */
    public function initialView(string $view): static
    {
        $this->attributes['view'] = CalendarLayout::checkView($view);

        return $this;
    }

    /** The date shown first, `YYYY-MM-DD`. */
    public function date(string $date): static
    {
        $this->attributes['date'] = CalendarLayout::checkDate($date, 'CalendarEntry->date()');

        return $this;
    }

    /**
     * The views offered, in this order.
     *
     * @param  array<array-key, string>  $views
     */
    public function views(array $views): static
    {
        $this->attributes['views'] = CalendarLayout::checkViews($views, 'CalendarEntry->views()');

        return $this;
    }
}

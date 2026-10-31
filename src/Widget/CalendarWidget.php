<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Layout\Calendar;
use Dskripchenko\LaravelAdmin\Widget\Concerns\ShowsResourceView;

/**
 * An event calendar on a dashboard: a resource's calendar view or a calendar
 * written as markup.
 *
 *     CalendarWidget::make()->title('This week')
 *         ->resource(MeetingResource::class)      // Resource::calendar()
 *         ->initialView('agenda');
 *
 *     CalendarWidget::make()->title('Releases')
 *         ->markup("2026-11-03 10:00-11:00 Release demo #release\nevery mon 11:00 Planning")
 *         ->initialView('month');
 *
 * The agenda and the week fit a widget best. In the resource mode the events
 * come from the resource's own calendar endpoint, and the widget is hidden
 * from a user who may not view the resource. See ShowsResourceView.
 */
class CalendarWidget extends Widget
{
    use ShowsResourceView;

    protected ?int $rowSpan = 3;

    private ?string $initialView = null;

    private ?string $date = null;

    /** @var list<string>|null */
    private ?array $views = null;

    public function widgetType(): string
    {
        return 'calendar';
    }

    protected function resourceView(): string
    {
        return 'calendar';
    }

    /**
     * The view shown first: day, week, month, year or agenda. In the
     * resource mode it replaces the view's own initial one.
     */
    public function initialView(string $view): static
    {
        $this->initialView = Calendar::checkView($view);

        return $this;
    }

    /** The date shown first, YYYY-MM-DD; today when not set. */
    public function date(string $date): static
    {
        $this->date = Calendar::checkDate($date, static::class.'::date()');

        return $this;
    }

    /**
     * The views offered, in this order.
     *
     * @param  array<array-key, string>  $views
     */
    public function views(array $views): static
    {
        $this->views = Calendar::checkViews($views, static::class.'::views()');

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->sourceData() + [
            'initialView' => $this->initialView,
            'date' => $this->date,
            'views' => $this->views,
        ];
    }
}

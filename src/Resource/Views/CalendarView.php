<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource\Views;

use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The calendar view of a resource: its records as events placed by a start
 * (and optionally an end) date or datetime column. Dragging an event moves
 * it, stretching it in the day and week grids changes its end.
 *
 *     public function calendar(): ?CalendarView
 *     {
 *         return CalendarView::make('starts_at', 'ends_at')
 *             ->title('name')
 *             ->color('type', ['meeting' => 'primary', 'deadline' => 'danger'])
 *             ->views(['month', 'week', 'day', 'agenda'])
 *             ->initialView('month');
 *     }
 */
final class CalendarView
{
    public const VIEWS = ['day', 'week', 'month', 'year', 'agenda'];

    public const TONES = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

    private ?string $allDay = null;

    private ?string $title = null;

    private ?string $colorColumn = null;

    /** @var array<array-key, string> */
    private array $colors = [];

    /** @var list<string> */
    private array $views = ['month', 'week', 'day', 'agenda'];

    private string $initialView = 'month';

    private bool $readonly = false;

    private function __construct(
        private readonly string $start,
        private readonly ?string $end,
    ) {}

    /**
     * @param  string  $start  The column the event starts at: a date or a datetime.
     * @param  string|null  $end  The column it ends at; none — a point in time (or one day).
     */
    public static function make(string $start, ?string $end = null): self
    {
        if ($start === '' || $end === '') {
            throw new InvalidArgumentException('CalendarView::make() needs a start column');
        }

        return new self($start, $end);
    }

    /**
     * A boolean column marking an all-day event. Without it, an event is all-day
     * when its columns hold dates rather than datetimes.
     */
    public function allDay(string $column): self
    {
        $this->allDay = $column;

        return $this;
    }

    /** The attribute (or dot path) for the event's title; recordTitle() by default. */
    public function title(string $path): self
    {
        $this->title = $path;

        return $this;
    }

    /**
     * The event's colour by the value of a column: value ⇒ tone (primary,
     * success, warning, danger, info, neutral) or a CSS colour.
     *
     * @param  array<array-key, string>  $tones
     */
    public function color(string $column, array $tones): self
    {
        $this->colorColumn = $column;
        $this->colors = $tones;

        return $this;
    }

    /**
     * The views offered: day, week, month, year, agenda.
     *
     * @param  list<string>  $views
     */
    public function views(array $views): self
    {
        $views = array_values(array_unique($views));
        foreach ($views as $view) {
            if (! in_array($view, self::VIEWS, true)) {
                throw new InvalidArgumentException("Unknown calendar view '{$view}'; expected one of ".implode(', ', self::VIEWS));
            }
        }
        if ($views === []) {
            throw new InvalidArgumentException('CalendarView::views() needs at least one view');
        }
        $this->views = $views;

        return $this;
    }

    public function initialView(string $view): self
    {
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException("Unknown calendar view '{$view}'; expected one of ".implode(', ', self::VIEWS));
        }
        $this->initialView = $view;

        return $this;
    }

    /** No dragging: the calendar only shows. */
    public function readonly(bool $readonly = true): self
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function startColumn(): string
    {
        return $this->start;
    }

    public function endColumn(): ?string
    {
        return $this->end;
    }

    public function allDayColumn(): ?string
    {
        return $this->allDay;
    }

    public function titlePath(): ?string
    {
        return $this->title;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * The relations the title's dot path reads, to eager-load.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        return $this->title !== null && str_contains($this->title, '.')
            ? [substr($this->title, 0, (int) strrpos($this->title, '.'))]
            : [];
    }

    /**
     * The tone (or CSS colour) of a record, or null.
     */
    public function colorOf(Model $record): ?string
    {
        if ($this->colorColumn === null) {
            return null;
        }
        $value = KanbanView::scalar($record->getAttribute($this->colorColumn));
        if ($value === null) {
            return null;
        }

        return $this->colors[$value] ?? null;
    }

    /**
     * Whether a column of the model holds a date without a time, judging by
     * its cast.
     */
    public static function isDateColumn(Model $model, string $column): bool
    {
        $cast = $model->getCasts()[$column] ?? null;
        if (! is_string($cast)) {
            return false;
        }
        $cast = strtolower(explode(':', $cast, 2)[0]);

        return $cast === 'date' || $cast === 'immutable_date';
    }

    /**
     * The view's description for the resource meta.
     *
     * @return array<string, mixed>
     */
    public function toArray(Resource $resource): array
    {
        $views = $this->views;
        $initial = in_array($this->initialView, $views, true) ? $this->initialView : $views[0];

        $model = isset($resource::$model) ? new ($resource::$model) : null;

        return [
            'start' => $this->start,
            // A date column takes 'Y-m-d'; the panel prefills and moves it so.
            'date_only' => $model instanceof Model && self::isDateColumn($model, $this->start),
            'end' => $this->end,
            'all_day' => $this->allDay,
            'title' => $this->title,
            'color' => $this->colorColumn,
            'views' => $views,
            'initial_view' => $initial,
            'readonly' => $this->readonly,
        ];
    }
}

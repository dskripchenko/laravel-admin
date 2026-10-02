<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Contracts\Renderable;
use Illuminate\Support\Str;

/**
 * The abstract widget — a component of a dashboard.
 *
 * Every widget has:
 *   - a `slug`, by default the kebab-cased class basename without the 'Widget'
 *     suffix; an instance may carry its own (withSlug), and two instances of
 *     one class on a dashboard get distinct ones (distinctSlugs);
 *   - a `widgetType()` — the UI type: stats, chart, table, markdown and so on;
 *   - a `data()` — the payload for the SPA, which may be computed lazily
 *     through the data endpoint;
 *   - a `view()` — the display configuration: the size, the refresh interval
 *     and the rest.
 *
 * The permission gating and the size are common to every widget.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget implements Renderable
{
    /**
     * The size on the dashboard's grid, in columns, 1..12.
     */
    protected int $size = 6;

    /**
     * The height on the dashboard's grid, in rows, 1..6; null lets the frontend pick by type.
     */
    protected ?int $rowSpan = null;

    protected ?string $title = null;

    protected ?int $refreshSeconds = null;

    /** @var list<string>|string|null */
    protected array|string|null $permission = null;

    /** @var bool|callable(): bool */
    protected $visibility = true;

    /**
     * Declared to depend on the dashboard's period — see periodAware().
     */
    protected bool $periodAware = false;

    private ?DashboardContext $dashboardContext = null;

    /** This instance's own slug — see withSlug(). */
    private ?string $instanceSlug = null;

    /**
     * Set once data() has read the context: such a widget depends on the
     * period whether or not it said so.
     */
    private bool $contextRead = false;

    /**
     * The widget's UI type: stats, chart, recent_list, table, markdown, iframe, heatmap or gauge.
     */
    abstract public function widgetType(): string;

    /**
     * The computed payload — the widget's actual content.
     *
     * It may throw, or return an empty array, when the data is to be loaded
     * lazily through WidgetController.fetch.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    public static function make(): static
    {
        return new static;
    }

    public static function slug(): string
    {
        $base = class_basename(static::class);
        if (str_ends_with($base, 'Widget')) {
            $base = substr($base, 0, -strlen('Widget'));
        }

        return Str::kebab($base);
    }

    /**
     * Gives this instance a slug of its own, in place of the class's.
     *
     * A dashboard tells its widgets apart by slug — in the SPA and in the
     * per-user layout it saves — so two instances of one class, two charts
     * say, need two. Without a name of their own the second and the next get
     * `-2`, `-3` appended in the order they are declared (distinctSlugs);
     * naming them keeps a saved layout attached when that order changes:
     *
     *     ChartWidget::make()->withSlug('revenue'),
     *     ChartWidget::make()->withSlug('signups'),
     */
    public function withSlug(string $slug): static
    {
        $this->instanceSlug = $slug;

        return $this;
    }

    /**
     * The slug this instance is known by: its own (withSlug) or the class's.
     */
    public function instanceSlug(): string
    {
        return $this->instanceSlug ?? static::slug();
    }

    /**
     * Makes the slugs of a set of widgets distinct: a widget whose slug is
     * already taken by an earlier one gets `-2`, `-3`, … appended. The first
     * keeps the slug as it is, so a layout saved before keeps pointing at it.
     *
     * @template T of Widget
     *
     * @param  list<T>  $widgets
     * @return list<T>
     */
    public static function distinctSlugs(array $widgets): array
    {
        $taken = [];
        foreach ($widgets as $widget) {
            $slug = $widget->instanceSlug();
            if (isset($taken[$slug])) {
                $n = 2;
                while (isset($taken[$slug.'-'.$n])) {
                    $n++;
                }
                $slug .= '-'.$n;
                $widget->withSlug($slug);
            }
            $taken[$slug] = true;
        }

        return $widgets;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The size on the dashboard's grid: 1..12, where 12 is the full width.
     */
    public function size(int $columns): static
    {
        $this->size = max(1, min($columns, 12));

        return $this;
    }

    /**
     * The height on the dashboard's grid: 1..6, where 1 is about 140px, 2
     * about 296px and so on. Left unset, the frontend picks by type: 2 for a
     * chart, 1 for a stat.
     */
    public function rowSpan(int $rows): static
    {
        $this->rowSpan = max(1, min($rows, 6));

        return $this;
    }

    public function refresh(int $seconds): static
    {
        $this->refreshSeconds = $seconds;

        return $this;
    }

    /**
     * @param  list<string>|string|null  $permission
     */
    public function permission(array|string|null $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @return list<string>|string|null
     */
    public function getPermission(): array|string|null
    {
        return $this->permission;
    }

    /**
     * @param  bool|callable(): bool  $cond
     */
    public function canSee(bool|callable $cond): static
    {
        $this->visibility = $cond;

        return $this;
    }

    public function isVisible(): bool
    {
        return is_callable($this->visibility)
            ? (bool) ($this->visibility)()
            : (bool) $this->visibility;
    }

    /**
     * Marks the widget as one whose data depends on the dashboard's period.
     * A dashboard shows its period switcher only when one of its widgets
     * does; a widget that reads dashboardContext() in data() is detected
     * without it.
     */
    public function periodAware(bool $aware = true): static
    {
        $this->periodAware = $aware;

        return $this;
    }

    public function isPeriodAware(): bool
    {
        return $this->periodAware || $this->contextRead;
    }

    /**
     * Hands the widget the dashboard's context before data() is computed.
     * The dashboard does it; a widget computed outside one gets the default.
     */
    public function withDashboardContext(DashboardContext $context): static
    {
        $this->dashboardContext = $context;

        return $this;
    }

    /**
     * The dashboard's context — the selected period first of all — for use
     * inside data().
     */
    public function dashboardContext(): DashboardContext
    {
        $this->contextRead = true;

        return $this->dashboardContext ??= new DashboardContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => 'widget',
            'slug' => $this->instanceSlug(),
            'type' => $this->widgetType(),
            'title' => is_string($this->title) ? \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->title) : $this->title,
            'size' => $this->size,
            'rowSpan' => $this->rowSpan,
            'refresh' => $this->refreshSeconds,
            'permission' => $this->permission,
            'data' => $this->data(),
        ];
    }
}

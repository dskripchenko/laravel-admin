<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Layout\Dashboard;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The abstract dashboard screen; a subclass declares its widgets through
 * `widgets()`.
 *
 * Everything that serves a dashboard — the manifest, the
 * `/dashboard/{get,save,widgets,...}` endpoints and layout() — goes through
 * this class, so that the same rules apply everywhere:
 *   1. the dashboard itself is open only to a user who holds `permission()`;
 *   2. its widgets are the declared ones plus the ones plugins registered
 *      through `$admin->widgets([...])`;
 *   3. a widget the user may not see — `Widget::permission()` or `canSee()` —
 *      is dropped before its data() is ever called;
 *   4. the per-user customization from admin_dashboard_layouts is applied
 *      on top, and cannot bring a dropped widget back;
 *   5. every widget gets the dashboard's context (the selected period).
 *
 * @method string|null name()
 */
abstract class DashboardScreen extends Screen
{
    /**
     * The current period: `all` or a number of days such as 7d, 30d, 90d.
     * The /dashboard/widgets endpoint passes it through withPeriod().
     */
    protected string $period = DashboardContext::DEFAULT_PERIOD;

    /**
     * Set once the screen has read its period — typically in widgets(), the
     * way dashboards were written before widgets had a context of their own.
     */
    private bool $periodRead = false;

    /** Whether withPeriod() chose the period; until then defaultPeriod() holds. */
    private bool $periodChosen = false;

    /** @var list<Widget>|null The declared and plugin widgets, built once per period. */
    private ?array $declared = null;

    /** @var list<Widget>|null The widgets the last compileWidgets() computed. */
    private ?array $computedWidgets = null;

    /**
     * The unique key, used as the `dashboard_key` of a DashboardLayout.
     */
    public function key(): string
    {
        return static::slug();
    }

    /**
     * The periods the switcher offers. null — the default — means automatic:
     * the standard 7d/30d/90d/all, shown only when a widget depends on the
     * period (see Widget::periodAware()) or the screen reads period() while
     * building its widgets. An empty array hides the switcher.
     *
     * @return list<string>|null
     */
    public function periods(): ?array
    {
        return null;
    }

    /**
     * The period a user starts with, before choosing one.
     */
    public function defaultPeriod(): string
    {
        return DashboardContext::DEFAULT_PERIOD;
    }

    /**
     * Tells whether a period may be selected on this dashboard.
     */
    public function acceptsPeriod(string $period): bool
    {
        $periods = $this->periods();

        return $periods === null
            ? DashboardContext::isValidPeriod($period)
            : in_array($period, $periods, true);
    }

    public function withPeriod(string $period): static
    {
        $this->period = $this->acceptsPeriod($period) ? $period : $this->defaultPeriod();
        $this->periodChosen = true;
        // widgets() may depend on the period, so they are built anew.
        $this->declared = null;
        $this->computedWidgets = null;

        return $this;
    }

    public function period(): string
    {
        $this->periodRead = true;

        return $this->currentPeriod();
    }

    /**
     * Converts the period into a number of days. For `all` it returns
     * something large — ten years — so that everything falls inside the
     * window.
     */
    public function periodDays(): int
    {
        return $this->dashboardContext()->days() ?? 365 * 10;
    }

    /**
     * The context every widget of this dashboard is computed with.
     */
    public function dashboardContext(): DashboardContext
    {
        $this->periodRead = true;

        return new DashboardContext($this->currentPeriod());
    }

    /**
     * @return list<Widget>
     */
    abstract public function widgets(): array;

    /**
     * @return Repository|array<string, mixed>
     */
    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    /**
     * Tells whether the user — the current one by default — may open this
     * dashboard. Every permission of permission() is required.
     */
    public function canAccess(?Model $user = null): bool
    {
        return self::allows($this->permission(), $user ?? $this->currentUser());
    }

    /**
     * The widgets the current user may see: the declared ones and the plugin
     * ones, deduplicated by slug, without the hidden and forbidden ones. Their
     * data is not computed here.
     *
     * @return list<Widget>
     */
    public function visibleWidgets(): array
    {
        return array_values(array_filter(
            $this->declaredWidgets(),
            fn (Widget $w): bool => $this->isWidgetVisible($w),
        ));
    }

    /**
     * The slugs of the widgets the dashboard has but the current user may not
     * see — what a saved layout must not refer to.
     *
     * @return list<string>
     */
    public function forbiddenWidgetSlugs(): array
    {
        $forbidden = [];
        foreach ($this->declaredWidgets() as $widget) {
            if (! $this->isWidgetVisible($widget)) {
                $forbidden[] = $widget->instanceSlug();
            }
        }

        return $forbidden;
    }

    /**
     * Drops from a saved layout the items that point at a widget the current
     * user may not see. Items of the user's own widgets, added in the SPA
     * with a key of their own, stay.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function filterLayoutItems(array $items): array
    {
        $forbidden = array_fill_keys($this->forbiddenWidgetSlugs(), true);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => ! isset($forbidden[(string) ($item['slug'] ?? '')]),
        ));
    }

    /**
     * Computes the visible widgets for the SPA, every one with the
     * dashboard's context.
     *
     * @return list<array<string, mixed>>
     */
    public function compileWidgets(): array
    {
        return array_map(
            fn (Widget $w): array => $w->withDashboardContext($this->contextForWidgets())->toArray(),
            $this->computedWidgets = $this->visibleWidgets(),
        );
    }

    /**
     * The dashboard's entry in the manifest.
     *
     * @return array<string, mixed>
     */
    public function toManifest(string $slug): array
    {
        $widgets = $this->compileWidgets();

        return [
            'slug' => $slug,
            'label' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->name() ?? $slug),
            'description' => \Dskripchenko\LaravelAdmin\I18n\Localize::string($this->description()),
            'permission' => $this->permission(),
            'periods' => $this->effectivePeriods(),
            'period' => $this->defaultPeriod(),
            'widgets' => $widgets,
        ];
    }

    /**
     * The periods the switcher shows, after the widgets were computed — that
     * is when it is known whether any of them depends on the period.
     *
     * @return list<string>
     */
    public function effectivePeriods(): array
    {
        $periods = $this->periods();
        if ($periods !== null) {
            return $periods;
        }

        $aware = $this->periodRead;
        foreach ($this->computedWidgets ?? [] as $widget) {
            $aware = $aware || $widget->isPeriodAware();
        }

        return $aware ? DashboardContext::DEFAULT_PERIODS : [];
    }

    /**
     * @return list<Layout>
     */
    public function layout(): array
    {
        $widgets = $this->effectiveWidgets();
        foreach ($widgets as $widget) {
            $widget->withDashboardContext($this->contextForWidgets());
        }

        return [
            Dashboard::make($widgets)->key($this->key()),
        ];
    }

    /**
     * The context handed to the widgets. Unlike dashboardContext() it does not
     * mark the screen itself as reading the period.
     */
    private function contextForWidgets(): DashboardContext
    {
        return new DashboardContext($this->currentPeriod());
    }

    private function currentPeriod(): string
    {
        if (! $this->periodChosen) {
            $this->period = $this->defaultPeriod();
        }

        return $this->period;
    }

    /**
     * Applies the per-user layout, when there is one, to the visible widgets.
     *
     * @return list<Widget>
     */
    private function effectiveWidgets(): array
    {
        $visibleBySlug = [];
        foreach ($this->visibleWidgets() as $w) {
            $visibleBySlug[$w->instanceSlug()] = $w;
        }

        $persisted = $this->loadPersistedLayout();
        if ($persisted === null) {
            return array_values($visibleBySlug);
        }

        $result = [];
        usort($persisted, static fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        foreach ($persisted as $item) {
            $slug = (string) ($item['slug'] ?? '');
            // Removed from the code since the layout was saved, or not for
            // this user: a saved layout never brings a widget in by itself.
            if (! isset($visibleBySlug[$slug])) {
                continue;
            }
            $widget = $visibleBySlug[$slug];
            unset($visibleBySlug[$slug]);

            if (($item['hidden'] ?? false) === true) {
                continue;
            }
            if (isset($item['size']) && is_int($item['size'])) {
                $widget->size($item['size']);
            }
            $result[] = $widget;
        }
        // The new widgets, the ones the persisted layout does not know, go last.
        foreach ($visibleBySlug as $widget) {
            $result[] = $widget;
        }

        return $result;
    }

    /**
     * The declared widgets and the plugin ones. A plugin's widget is dropped
     * when the screen places that class itself — with its own title or size —
     * so the host does not get a second copy appended; the screen's own
     * widgets all stay, two instances of one class included, and get
     * distinct slugs (Widget::distinctSlugs). Memoised, so that a plugin
     * widget is built once.
     *
     * @return list<Widget>
     */
    private function declaredWidgets(): array
    {
        if ($this->declared !== null) {
            return $this->declared;
        }

        $widgets = $this->widgets();
        $placed = [];
        foreach ($widgets as $w) {
            $placed[$w::slug()] = true;
        }
        foreach ($this->pluginWidgets() as $w) {
            if (! isset($placed[$w::slug()])) {
                $placed[$w::slug()] = true;
                $widgets[] = $w;
            }
        }

        return $this->declared = Widget::distinctSlugs($widgets);
    }

    /**
     * The widgets the plugins registered through `$admin->widgets([...])`.
     *
     * Until 1.30 that registry had no reader at all: a pack could register a
     * widget and it would never appear anywhere, which is how the queue-depth
     * widget of laravel-admin-jobs came to be written twice. A dashboard of the
     * current panel picks them up automatically, after its own — the host
     * declares what its screen is about, a plugin adds to the end.
     *
     * Resolved through the container, so a widget may take its data source as a
     * constructor dependency. One that cannot be built is skipped: a plugin
     * with a broken binding must not take the dashboard down.
     *
     * @return list<Widget>
     */
    private function pluginWidgets(): array
    {
        $panel = \Dskripchenko\LaravelAdmin\Panel\Panels::current()->id;
        $widgets = [];

        foreach (app(\Dskripchenko\LaravelAdmin\Admin::class)->getWidgets($panel) as $class) {
            try {
                $widget = app($class);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($widget instanceof Widget) {
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function loadPersistedLayout(): ?array
    {
        $user = $this->currentUser();
        if ($user === null) {
            return null;
        }

        $layout = DashboardLayout::query()
            ->where('dashboard_key', $this->key())
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->first();

        if ($layout === null) {
            return null;
        }

        return $layout->widgets;
    }

    private function currentUser(): ?Model
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        return $user instanceof Model ? $user : null;
    }

    private function isWidgetVisible(Widget $widget): bool
    {
        return $widget->isVisible() && self::allows($widget->getPermission(), $this->currentUser());
    }

    /**
     * Every listed permission is required, as in the AdminAccess middleware;
     * a user model without `hasAccess()` holds none.
     *
     * @param  list<string>|string|null  $permission
     */
    private static function allows(array|string|null $permission, ?Model $user): bool
    {
        $permissions = array_values(array_filter(
            is_array($permission) ? $permission : [$permission],
            static fn (mixed $p): bool => is_string($p) && trim($p) !== '',
        ));
        if ($permissions === []) {
            return true;
        }
        if ($user === null || ! method_exists($user, 'hasAccess')) {
            return false;
        }

        foreach ($permissions as $p) {
            if (! $user->hasAccess($p)) {
                return false;
            }
        }

        return true;
    }
}

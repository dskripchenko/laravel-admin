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
 * compile() and layout() then, by themselves:
 *   1. apply the per-user customization from admin_dashboard_layouts, when
 *      there is one;
 *   2. hide the widgets the user has no permission for;
 *   3. wrap the result into a Dashboard layout with key=$this->key().
 *
 * @method string|null name()
 */
abstract class DashboardScreen extends Screen
{
    /**
     * The current period: 7d, 30d, 90d or all. The /dashboard/widgets
     * endpoint passes it through withPeriod(); 30d by default.
     */
    protected string $period = '30d';

    /**
     * The unique key, used as the `dashboard_key` of a DashboardLayout.
     */
    public function key(): string
    {
        return static::slug();
    }

    public function withPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function period(): string
    {
        return $this->period;
    }

    /**
     * Converts a period into a number of days. For 'all' it returns something
     * large — ten years — so that everything falls inside the window.
     */
    public function periodDays(): int
    {
        return match ($this->period) {
            '7d' => 7,
            '90d' => 90,
            'all' => 365 * 10,
            default => 30,
        };
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
     * @return list<Layout>
     */
    public function layout(): array
    {
        $widgets = $this->effectiveWidgets();

        return [
            Dashboard::make($widgets)->key($this->key()),
        ];
    }

    /**
     * Applies the per-user layout, when there is one, and filters by permission.
     *
     * @return list<Widget>
     */
    private function effectiveWidgets(): array
    {
        // Deduplicated by slug, the screen's own instance winning: a host that
        // places a plugin's widget itself — with its own title or size — must
        // not then get a second copy of it appended.
        $declaredBySlug = [];
        foreach ([...$this->widgets(), ...$this->pluginWidgets()] as $w) {
            $declaredBySlug[$w::slug()] ??= $w;
        }
        $declared = array_values($declaredBySlug);

        $persisted = $this->loadPersistedLayout();

        // Assembling the final list.
        $result = [];
        if ($persisted !== null) {
            usort($persisted, static fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
            foreach ($persisted as $item) {
                $slug = (string) ($item['slug'] ?? '');
                if (! isset($declaredBySlug[$slug])) {
                    continue; // Widget удалён из кода с момента сохранения layout'а.
                }
                $widget = $declaredBySlug[$slug];
                unset($declaredBySlug[$slug]);

                if (($item['hidden'] ?? false) === true) {
                    continue;
                }
                if (isset($item['size']) && is_int($item['size'])) {
                    $widget->size($item['size']);
                }
                $result[] = $widget;
            }
            // The new widgets, the ones the persisted layout does not know, go last.
            foreach ($declaredBySlug as $widget) {
                $result[] = $widget;
            }
        } else {
            $result = $declared;
        }

        return array_values(array_filter($result, fn (Widget $w): bool => $this->isWidgetVisible($w)));
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
        if (! $widget->isVisible()) {
            return false;
        }

        $permission = $widget->getPermission();
        if ($permission === null) {
            return true;
        }

        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }
        if (! method_exists($user, 'hasAccess')) {
            return true;
        }

        $permissions = is_array($permission) ? $permission : [$permission];
        foreach ($permissions as $p) {
            if (! $user->hasAccess($p)) {
                return false;
            }
        }

        return true;
    }
}

---
title: Widgets & Dashboards
audience: developer
status: stable
locale: en
---

# Widgets & Dashboards

A **Dashboard** is a Screen that hosts a 12-column grid of **Widgets**.

```php
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;

final class ContentDashboardScreen extends DashboardScreen
{
    public static function slug(): string { return 'content'; }
    public function name(): string { return 'Analytics' ;}

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->title('Articles')
                ->size(3)
                ->stat('TOTAL', Article::count())
                ->trend(12.4, 'up'),

            ChartWidget::make()
                ->title('Daily publications')
                ->size(8)
                ->rowSpan(2)
                ->chartType('bar')
                ->labels($days)
                ->dataset('Published', $values, '#10b981'),
        ];
    }
}
```

Register: `Admin::screen([ContentDashboardScreen::class])`. URL:
`/admin/dashboard/content`.

## Built-in widget types

| Class | `widgetType()` | Use |
|---|---|---|
| `StatsOverviewWidget` | `stats` | Single value + trend; KPI cards. |
| `ChartWidget` | `chart` | Line / bar / area / radar (any number of `dataset()` series, `stacked()` for bar and area) and doughnut / pie. |
| `RecentListWidget` | `recent_list` | Last N rows of an Eloquent model. |
| `MarkdownWidget` | `markdown` | Static rich text. |
| `IframeWidget` | `iframe` | Embed external URL. |
| `TableWidget` | `table` | Flat read-only data. |
| `HeatmapWidget` | `heatmap` | Matrix `rows × cols × value` (e.g. activity by hour). |
| `GaugeWidget` | `gauge` | Single value 0..max with thresholds. |

## Sizing

Each widget has `size()` (1..12 cols, default 6) and optional
`rowSpan()` (1..6 rows of `140px`, default by type: stat=1,
chart/heatmap=2..3).

```php
ChartWidget::make()->size(8)->rowSpan(2);    // ~half-width, ~296px tall
StatsOverviewWidget::make()->size(3);        // quarter-width, default rowSpan=1
```

User can override both axes in edit-mode (drag bottom-right corner).

## Polling

```php
ChartWidget::make()
    ->title('Live signups')
    ->refresh(30);   // re-fetch every 30 seconds
```

The frontend computes the minimum `refresh` over visible widgets and
polls `/api/admin/dashboard/widgets?key={slug}&period={p}` once per
interval. One timer for the whole dashboard.

## Edit mode

Click "Edit" in the dashboard toolbar. For each widget overlays appear:

- **☰** drag-handle — reorder
- **⚙** configure — open widget config dialog (title/size/type-specific)
- **×** remove (or hide if it's a manifest widget — soft override)
- **↘** resize — drag both axes (X=cols, Y=rows)

User can also **+ Add widget** — opens the type-picker dialog. Custom
widgets get `slug = "custom.{type}.{timestamp}"`.

Save → POST `/api/admin/dashboard/save` with the full widget array.
Persisted per user in `admin_dashboard_layouts`.

## Per-user overrides

The model is:

```
Manifest declares widgets (host code defines the canonical layout).
User layout (DashboardLayout row) sits on top — same slugs, different
{size, position, hidden, rowSpan}; plus custom-added widgets.
```

If the manifest changes (new widget added in code), it appears at the
end of the user's grid by default.

## Permissions

A dashboard and each of its widgets can be guarded:

```php
final class SalesDashboardScreen extends DashboardScreen
{
    public function permission(): array|string|null
    {
        return 'sales.dashboard';            // an array means "all of them"
    }

    public function widgets(): array
    {
        return [
            RevenueWidget::make()->permission('sales.revenue'),
            OrdersWidget::make()->canSee(fn () => auth('admin')->user()?->is_manager),
        ];
    }
}
```

The rules are enforced on the server, wherever the dashboard is served:

- a dashboard the user may not open is left out of the manifest and of the
  menu, and every `/api/admin/dashboard/*` call for it answers `403`;
- a widget the user may not see is dropped before its `data()` is called — its
  queries never run — in the manifest, in `dashboard/widgets` (period changes
  and polling) and in `layout()`;
- a saved per-user layout cannot bring such a widget back: `dashboard/get`
  and `dashboard/save` strip it.

Put the expensive work inside the widget's `data()`. Whatever `widgets()`
computes while building the list (for example `->stat('TOTAL', Article::count())`)
runs for every user who can open the dashboard, whatever the widget's own
permission.

## Period

The dashboard toolbar has a period switcher (7 / 30 / 90 days / all time). The
selected period is sent to `/api/admin/dashboard/widgets?key={slug}&period={p}`,
saved per user, and handed to every widget as a `DashboardContext`:

```php
use Dskripchenko\LaravelAdmin\Widget\Widget;

class NewOrdersWidget extends Widget
{
    public function widgetType(): string { return 'stats'; }

    public function data(): array
    {
        $context = $this->dashboardContext();   // ->period, ->days(), ->from(), ->to()

        $count = $context->constrain(Order::query(), 'created_at')->count();

        return ['stats' => [['label' => 'New orders', 'value' => $count]]];
    }
}
```

`DashboardContext::constrain($query, $column)` adds `where($column, '>=', from)`
and leaves the query alone for `all`. A period is `all` or a number of days
followed by `d` (`7d`, `14d`, `90d`).

The built-in list widgets opt in with `withinPeriod()`:

```php
RecentListWidget::make()->model(Order::class)->column('number')->withinPeriod();
TableWidget::make()->model(Order::class)->withinPeriod('paid_at');
```

A dashboard screen can also read the period itself while building its widgets —
`$this->period()`, `$this->periodDays()` or `$this->dashboardContext()` — which
is how dashboards were written before widgets had a context. Widgets that ignore
the period keep working unchanged.

The switcher is shown only when something on the dashboard depends on the
period: a widget that reads `dashboardContext()` in `data()`, one marked with
`->periodAware()` (or `withinPeriod()`), or a screen that reads its period in
`widgets()`. To choose the periods explicitly:

```php
public function periods(): ?array
{
    return ['7d', '14d', '30d'];   // [] hides the switcher, null = automatic
}

public function defaultPeriod(): string
{
    return '14d';
}
```

## Custom widgets

```php
namespace App\Admin\Widgets;

use Dskripchenko\LaravelAdmin\Widget\Widget;

class WeatherWidget extends Widget
{
    public static function slug(): string { return 'weather'; }
    public function widgetType(): string { return 'weather'; }
    public function data(): array
    {
        return ['temp' => 23, 'icon' => 'sunny', 'city' => 'Moscow'];
    }
}
```

```php
public function widgets(): array
{
    return [WeatherWidget::make()->title('Weather')->size(3)];
}
```

Frontend: register a Vue component for the type:

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './WeatherWidget.vue'
registerWidget('weather', WeatherWidget)
```

## Plugin widgets

A package has nowhere to declare a widget — the dashboard class belongs to the
host. So `$admin->widgets([...])` registers widget classes, and every
`DashboardScreen` of the current panel picks them up, after its own:

```php
public function boot(Admin $admin): void
{
    $admin->widgets([QueueDepthWidget::class]);
}
```

They appear wherever the dashboard is served — the manifest, the
`dashboard/widgets` refresh and the saved layouts — under the same permission
rules as declared widgets, so a plugin widget that sets `permission()` is shown
only to the users who hold it. The widget is built through the container, so it
may ask for dependencies in its constructor. Duplicates are dropped by slug: if
the host placed the same widget itself, with its own title or size, no second
copy is added. A widget that cannot be built is skipped, so a broken plugin
binding does not take the dashboard down.

## Header indicators

A neighbouring mechanism for the same case — a package has something to say
and nowhere to put it: `$admin->statusIndicators([...])` and the
`Dskripchenko\LaravelAdmin\Status\StatusIndicator` interface. The panel
draws the indicator, the plugin answers for its state (`key()` and
`state()`) — see [system.status](../../ru/api/system.md) (in Russian).

## See also

- [Screens](screens.md) — `DashboardScreen` extends `Screen`
- [Permissions](permissions.md)
- [Architecture](../architecture.md) — Widget toArray shape

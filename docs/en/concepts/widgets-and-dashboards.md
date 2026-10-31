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

`stat()` takes the raw number; the panel formats it in its locale. For money
call `money()` on the card: `->stat('Revenue', $revenue)->money('USD')` shows
`$1,591,285` in English and `1 591 285 $` in Russian. `precision()`,
`prefix()` and `suffix()` also describe the card added last.

Register: `Admin::screen([ContentDashboardScreen::class])`. URL:
`/admin/dashboard/content`.

## Built-in widget types

| Class | `widgetType()` | Use |
|---|---|---|
| `StatsOverviewWidget` | `stats` | Single value + trend; KPI cards. |
| `ChartWidget` | `chart` | Line / bar / area / radar (any number of `dataset()` series, `stacked()` for bar and area) and doughnut / pie. |
| `RecentListWidget` | `recent_list` | Last N rows of an Eloquent model. |
| `MarkdownWidget` | `markdown` | Static rich text; ```` ```mermaid ```` blocks become diagrams. |
| `IframeWidget` | `iframe` | Embed external URL. |
| `TableWidget` | `table` | Flat read-only data. |
| `HeatmapWidget` | `heatmap` | Matrix `rows × cols × value` (e.g. activity by hour). |
| `GaugeWidget` | `gauge` | Single value 0..max with thresholds. |
| `DiagramWidget` | `diagram` | A diagram from text: Mermaid, PlantUML, DOT, D2, DBML, Structurizr, Wardley. |
| `BpmnWidget` | `bpmn` | A BPMN process or a DMN decision model, read-only. |
| `KanbanWidget` | `kanban` | A resource's kanban view, or a board written as markup. |
| `CalendarWidget` | `calendar` | A resource's calendar view, or a calendar written as markup. |

## Formatting values

`RecentListWidget` columns take a `TableColumn`, so they format exactly like a
resource list: `format()`, `asMoney()`, `asDate()`/`asDateTime()` with PHP
formats, `asBadge()` with labels, `asLink()`, `align()`. The SPA draws them with
the same cell renderer, in the panel's locale. A name and a label still work:

```php
RecentListWidget::make()
    ->model(Order::class)
    ->column('number', 'Number')
    ->column(TableColumn::make('total')->label('Total')->asMoney('USD')->align('right'))
    ->column(TableColumn::make('status')->asBadge(['paid' => ['label' => 'Paid', 'tone' => 'success']]))
    ->column(TableColumn::make('created_at')->label('Placed')->asDateTime('d.m.Y H:i'))
    ->linkTo('orders');
```

`TableWidget::columns()` takes the same `TableColumn`s. A chart shows its values
(tooltips, the data table, the axis) as money with `ChartWidget::money('USD')`,
or with fixed decimals with `precision(2)`; the datasets keep the raw numbers.

## Heatmap

`HeatmapWidget` draws a rows × columns matrix with the kit's `UidHeatmapMatrix`.
Every column is labelled (rotated and thinned when the labels do not fit), a
cell shows "row × column: value" on hover and focus, and a legend runs from the
smallest value to the largest.

```php
HeatmapWidget::make()->title('Orders by weekday and month')
    ->axes(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'])
    ->matrix($rows)          // $rows[$weekday][$month]: int|float|null
    ->colorScale('viridis')
    ->precision(1);
```

- `matrix()` is sized to the axes. `null` — or a missing cell — is "no data": an
  empty outlined cell, unlike `0`. Use it for a day that has not come yet.
- `colorScale()` takes `default` (the panel's accent, the default), `viridis`,
  `magma`, `plasma`, `inferno`, `blues`, `greens`, `reds`, one CSS colour, or a
  list of CSS colours as custom stops from low to high.
- `range($min, $max)` fixes the colour domain; by default it is the matrix's
  own min and max.
- `money('USD')` and `precision(2)` format the tooltip and the legend, in the
  panel's locale, like a chart.

## Diagrams, BPMN, kanban and calendar

Four widgets show documents and the resource views of the list page. Each
scrolls its content inside the widget, so the widget keeps the height its
`rowSpan()` gives it (3 rows by default; the kanban is full width by default).

```php
use Dskripchenko\LaravelAdmin\Widget\{BpmnWidget, CalendarWidget, DiagramWidget, KanbanWidget};

DiagramWidget::make()->title('Order flow')
    ->content("flowchart LR\n  new --> paid --> shipped")   // or a callable
    ->syntax('mermaid');                                       // detected from the text when unset

BpmnWidget::make()->title('Order process')
    ->xml(fn () => Storage::get('processes/order.bpmn'))
    ->highlight(['Task_approve']);
BpmnWidget::make()->title('Discounts')->dmn()->url('/files/discount.dmn')->decision('Decision_1');

KanbanWidget::make()->title('Open tasks')
    ->resource(TaskResource::class)          // its Resource::kanban() view
    ->filters(['assignee' => 'me'])          // the list's filters, by name
    ->search('urgent')
    ->editable();                            // drag cards — only for a user who may update

CalendarWidget::make()->title('This week')
    ->resource(MeetingResource::class)       // its Resource::calendar() view
    ->initialView('agenda');                 // day, week, month, year or agenda

KanbanWidget::make()->title('Roadmap')->markup("## Now\n- Billing #api\n\n## Next\n- Reports");
CalendarWidget::make()->markup("every mon 10:00-10:30 Planning")->initialView('week');
```

- `DiagramWidget` draws like [`Layout::diagram()`](../layouts-reference.md);
  `syntax()` takes `mermaid`, `plantuml`, `dot`, `d2`, `dbml`, `structurizr`
  or `wardley`.
- `BpmnWidget` shows the model read-only with pan and zoom: `xml()` (a string
  or a callable) or `url()` — a path of the panel's site or a URL of the same
  origin, loaded with the user's session; `dmn()`, `highlight()`,
  `decision()` and `showDiagram()` as on the BPMN/DMN layouts.
- `KanbanWidget` and `CalendarWidget` take **a resource** or **markup**. With
  `resource()` the widget is the resource's own view, as on its list page —
  the cards and events come from its `board` and `calendar` endpoints, with
  its scope, filters and permissions; a click opens the record. It is
  read-only unless `editable()`, and even then only for a user who holds the
  resource's update permission (and never on a view declared `readonly()`).
  The widget is hidden from a user without the resource's view permission and
  when the resource is not registered in the current panel or does not
  declare that view. With `markup()` it is the kit's `UidKanban` /
  `UidEventCalendar`, read-only, in the
  [same markup](../layouts-reference.md#kanban-and-calendar) as
  `Layout::kanban()` / `Layout::calendar()`.
- `CalendarWidget::initialView()` replaces the resource view's first view (and
  is added to its views when missing); `views()` sets the views offered, and
  `date()` the first date of a markup calendar.

### Added by the user

The same four types can be added in **Edit → Add widget**; the dialog asks:

| Type | Settings |
|---|---|
| Diagram | The notation (or detected from the text) and the source |
| BPMN / DMN | The notation, and an address of a file on the panel's site or the whole XML |
| Kanban | The source: a resource — only those with a kanban view the user may view — with an optional search and "allow dragging", or markup |
| Calendar | The same, for resources with a calendar view, plus the first view (the agenda by default) |

The settings are saved as the widget's `config` in the user's layout. A
user-added kanban or calendar over a resource obeys the same rules as the
class: `dashboard/save` and `dashboard/get` drop one whose resource the user
may not view (or that lost the view), and the data goes through the
resource's own endpoints, which check the permissions again. How a host adds
its own types to the dialog is in
[Frontend extension](../frontend-extension.md#letting-users-add-it).

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

### Several widgets of one class

A dashboard tells its widgets apart by slug, and a widget's slug comes from its
class — so two `ChartWidget`s would share one. They don't: the second and the
next instance of a slug get `-2`, `-3` appended in the order they are declared
(`chart`, `chart-2`). The first keeps the plain slug, so a layout saved before
still points at it.

Since a suffix follows the declaration order, name the instances when the order
may change, and the saved layouts stay attached to the right widget:

```php
public function widgets(): array
{
    return [
        ChartWidget::make()->withSlug('revenue')->title('Revenue'),
        ChartWidget::make()->withSlug('signups')->title('Sign-ups'),
    ];
}
```

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

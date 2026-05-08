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
| `ChartWidget` | `chart` | Bar / line / area / doughnut / pie. |
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
Persisted in `dashboard_layouts` per-user.

## Per-user overrides

The model is:

```
Manifest declares widgets (host code defines the canonical layout).
User layout (DashboardLayout row) sits on top — same slugs, different
{size, position, hidden, rowSpan}; plus custom-added widgets.
```

If the manifest changes (new widget added in code), it appears at the
end of the user's grid by default.

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

## See also

- [Screens](screens.md) — `DashboardScreen` extends `Screen`
- [Permissions](permissions.md)
- [Architecture](../architecture.md) — Widget toArray shape

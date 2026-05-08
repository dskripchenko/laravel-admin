---
title: Widgets & Dashboards
audience: developer
status: stable
locale: ru
translated_from: en/concepts/widgets-and-dashboards.md
translated_at: 2026-05-08
---

# Widgets & Dashboards

**Dashboard** — это Screen, хостящий 12-колоночный grid из **Widget'ов**.

```php
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;

final class ContentDashboardScreen extends DashboardScreen
{
    public static function slug(): string { return 'content'; }
    public function name(): string { return 'Аналитика'; }

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->title('Статьи')
                ->size(3)
                ->stat('TOTAL', Article::count())
                ->trend(12.4, 'up'),

            ChartWidget::make()
                ->title('Публикации по дням')
                ->size(8)
                ->rowSpan(2)
                ->chartType('bar')
                ->labels($days)
                ->dataset('Опубликовано', $values, '#10b981'),
        ];
    }
}
```

Регистрация: `Admin::screen([ContentDashboardScreen::class])`. URL:
`/admin/dashboard/content`.

## Встроенные типы виджетов

| Класс | `widgetType()` | Назначение |
|---|---|---|
| `StatsOverviewWidget` | `stats` | KPI-карточка с числом + trend. |
| `ChartWidget` | `chart` | Bar / line / area / doughnut / pie. |
| `RecentListWidget` | `recent_list` | Последние N записей Eloquent-модели. |
| `MarkdownWidget` | `markdown` | Статичный rich text. |
| `IframeWidget` | `iframe` | Embed внешнего URL. |
| `TableWidget` | `table` | Плоские read-only данные. |
| `HeatmapWidget` | `heatmap` | Матрица `rows × cols × value`. |
| `GaugeWidget` | `gauge` | Одно значение 0..max с зонами. |

## Размеры

Каждый widget имеет `size()` (1..12 cols, default 6) и опциональный
`rowSpan()` (1..6 rows × 140px, default по типу: stat=1,
chart/heatmap=2..3).

```php
ChartWidget::make()->size(8)->rowSpan(2);    // ~half-width, ~296px высота
StatsOverviewWidget::make()->size(3);        // quarter-width, default rowSpan=1
```

В edit-mode пользователь может override обе оси (drag за нижний-правый
угол).

## Polling

```php
ChartWidget::make()->title('Live signups')->refresh(30);  // каждые 30 секунд
```

Frontend считает минимальный `refresh` среди видимых виджетов и
пуллит `/api/admin/dashboard/widgets?key={slug}&period={p}` с этим
интервалом. Один таймер на весь dashboard.

## Edit-mode

Click "Редактировать" в toolbar. На каждом виджете overlays:

- **☰** drag-handle — reorder
- **⚙** configure — диалог настройки (title/size/type-specific)
- **×** remove (или hide для manifest-widget — soft override)
- **↘** resize — drag по обеим осям

`+ Add widget` — открывает type-picker. Custom-виджет получает
`slug = "custom.{type}.{timestamp}"`.

Save → POST `/api/admin/dashboard/save`. Сохраняется в
`dashboard_layouts` per-user.

## Per-user override'ы

Модель:

```
Manifest объявляет widgets (host-код задаёт canonical layout).
User layout (DashboardLayout row) сидит поверх — те же slug'и, разные
{size, position, hidden, rowSpan}; плюс custom-добавленные виджеты.
```

Если manifest меняется (новый widget в коде), он появляется в конце
user grid'а по умолчанию.

## Custom widget

```php
namespace App\Admin\Widgets;

use Dskripchenko\LaravelAdmin\Widget\Widget;

class WeatherWidget extends Widget
{
    public static function slug(): string { return 'weather'; }
    public function widgetType(): string { return 'weather'; }
    public function data(): array
    {
        return ['temp' => 23, 'icon' => 'sunny', 'city' => 'Москва'];
    }
}
```

Frontend регистрирует Vue-компонент:

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './WeatherWidget.vue'
registerWidget('weather', WeatherWidget)
```

## См. также

- [Screens](screens.md)
- [Permissions](../en/concepts/permissions.md) (en)
- [Архитектура](../architecture.md)

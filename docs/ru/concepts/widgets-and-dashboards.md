---
title: Widgets & Dashboards
audience: developer
status: stable
locale: ru
translated_from: en/concepts/widgets-and-dashboards.md
translated_at: 2026-10-02
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

`stat()` принимает число как есть, панель форматирует его в своей локали. Для
денег вызовите на карточке `money()`: `->stat('Выручка', $revenue)->money('USD')`
покажет `$1,591,285` по-английски и `1 591 285 $` по-русски. `precision()`,
`prefix()` и `suffix()` тоже описывают последнюю добавленную карточку.

Регистрация: `Admin::screen([ContentDashboardScreen::class])`. URL:
`/admin/dashboard/content`.

## Встроенные типы виджетов

| Класс | `widgetType()` | Назначение |
|---|---|---|
| `StatsOverviewWidget` | `stats` | KPI-карточка с числом + trend. |
| `ChartWidget` | `chart` | Line / bar / area / radar (сколько угодно серий `dataset()`, `stacked()` для bar и area) и doughnut / pie. |
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

Save → POST `/api/admin/dashboard/save` с полным массивом виджетов.
Сохраняется per-user в `admin_dashboard_layouts`.

## Per-user override'ы

Модель:

```
Manifest объявляет widgets (host-код задаёт canonical layout).
User layout (DashboardLayout row) сидит поверх — те же slug'и, разные
{size, position, hidden, rowSpan}; плюс custom-добавленные виджеты.
```

Если manifest меняется (новый widget в коде), он появляется в конце
user grid'а по умолчанию.

### Несколько виджетов одного класса

Дашборд различает виджеты по slug, а slug виджета берётся из его класса — так
что два `ChartWidget` делили бы один. Не делят: второй и следующие экземпляры
получают `-2`, `-3` в порядке объявления (`chart`, `chart-2`). Первый
сохраняет slug как есть, так что сохранённый раньше layout указывает на него
же.

Суффикс зависит от порядка объявления, поэтому если порядок может меняться,
назовите экземпляры — сохранённые layout'ы останутся привязаны к нужному
виджету:

```php
public function widgets(): array
{
    return [
        ChartWidget::make()->withSlug('revenue')->title('Выручка'),
        ChartWidget::make()->withSlug('signups')->title('Регистрации'),
    ];
}
```

## Права доступа

Закрыть можно и дашборд, и каждый виджет:

```php
final class SalesDashboardScreen extends DashboardScreen
{
    public function permission(): array|string|null
    {
        return 'sales.dashboard';            // массив — нужны все права
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

Правила проверяются на сервере везде, где отдаётся дашборд:

- дашборд, к которому у пользователя нет доступа, не попадает ни в манифест,
  ни в меню, а любой вызов `/api/admin/dashboard/*` для него отвечает `403`;
- виджет, который пользователю не положен, отбрасывается до вызова `data()` —
  его запросы не выполняются — в манифесте, в `dashboard/widgets` (смена
  периода и polling) и в `layout()`;
- сохранённый layout такой виджет не вернёт: `dashboard/get` и
  `dashboard/save` его вычищают.

Дорогие вычисления держите внутри `data()` виджета. То, что считается прямо в
`widgets()` при сборке списка (например `->stat('TOTAL', Article::count())`),
выполняется для каждого, кто может открыть дашборд, независимо от прав самого
виджета.

## Период

В тулбаре дашборда есть переключатель периода (7 / 30 / 90 дней / всё время).
Выбранный период уходит в `/api/admin/dashboard/widgets?key={slug}&period={p}`,
сохраняется для пользователя и передаётся каждому виджету как
`DashboardContext`:

```php
use Dskripchenko\LaravelAdmin\Widget\Widget;

class NewOrdersWidget extends Widget
{
    public function widgetType(): string { return 'stats'; }

    public function data(): array
    {
        $context = $this->dashboardContext();   // ->period, ->days(), ->from(), ->to()

        $count = $context->constrain(Order::query(), 'created_at')->count();

        return ['stats' => [['label' => 'Новые заказы', 'value' => $count]]];
    }
}
```

`DashboardContext::constrain($query, $column)` добавляет
`where($column, '>=', from)`, а для `all` оставляет запрос как есть. Период — это
`all` или число дней с суффиксом `d` (`7d`, `14d`, `90d`).

Встроенные списочные виджеты включают фильтр через `withinPeriod()`:

```php
RecentListWidget::make()->model(Order::class)->column('number')->withinPeriod();
TableWidget::make()->model(Order::class)->withinPeriod('paid_at');
```

Экран дашборда может и сам читать период при сборке виджетов —
`$this->period()`, `$this->periodDays()` или `$this->dashboardContext()`: так
дашборды писались до появления контекста у виджетов. Виджеты, которым период
безразличен, работают как раньше.

Переключатель показывается, только если от периода что-то зависит: виджет читает
`dashboardContext()` в `data()`, помечен `->periodAware()` (или
`withinPeriod()`), либо экран читает период в `widgets()`. Задать набор явно:

```php
public function periods(): ?array
{
    return ['7d', '14d', '30d'];   // [] скрывает переключатель, null — автоматически
}

public function defaultPeriod(): string
{
    return '14d';
}
```

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

```php
public function widgets(): array
{
    return [WeatherWidget::make()->title('Погода')->size(3)];
}
```

Frontend регистрирует Vue-компонент для этого типа:

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './WeatherWidget.vue'
registerWidget('weather', WeatherWidget)
```

## Виджеты от плагинов

Пакету негде объявить виджет — класс дашборда принадлежит хосту. Поэтому
`$admin->widgets([...])` регистрирует классы, а любой `DashboardScreen` текущей
панели подхватывает их сам, после собственных:

```php
public function boot(Admin $admin): void
{
    $admin->widgets([QueueDepthWidget::class]);
}
```

Виджет собирается через контейнер, так что зависимость можно попросить в
конструкторе. Такие виджеты появляются везде, где отдаётся дашборд, — в
манифесте, в обновлении `dashboard/widgets` и в сохранённых layout'ах — по тем
же правилам доступа, что и объявленные: виджет плагина с `permission()` видят
только те, у кого это право. Дубли отсекаются по slug: если хост поставил тот же виджет сам —
со своим заголовком или размером, — второй копии не появится. Виджет, который не
удалось собрать, пропускается: сломанный биндинг плагина не должен ронять
дашборд.

До 1.30.0 у этого реестра не было ни одного читателя — зарегистрированный виджет
не появлялся нигде.

## Индикаторы в шапке

Соседний механизм для того же случая «пакету есть что сказать, а места нет»:
`$admin->statusIndicators([...])` и интерфейс
`Dskripchenko\LaravelAdmin\Status\StatusIndicator`. Панель рисует, плагин
отвечает — см. [system.status](../api/system.md).

## См. также

- [Screens](screens.md) — `DashboardScreen` наследует `Screen`
- [Permissions](permissions.md)
- [Архитектура](../architecture.md) — форма `toArray()` виджета

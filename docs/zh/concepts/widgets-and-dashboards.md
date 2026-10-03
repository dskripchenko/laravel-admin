---
title: 小部件与仪表板
audience: developer
status: stable
locale: zh
translated_from: en/concepts/widgets-and-dashboards.md
translated_at: 2026-10-02
---

# 小部件与仪表板

**仪表板（Dashboard）**是一个承载 12 列网格**小部件（Widget）**的 Screen。

```php
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;

final class ContentDashboardScreen extends DashboardScreen
{
    public static function slug(): string { return 'content'; }
    public function name(): string { return '分析' ;}

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->title('文章')
                ->size(3)
                ->stat('TOTAL', Article::count())
                ->trend(12.4, 'up'),

            ChartWidget::make()
                ->title('每日发布量')
                ->size(8)
                ->rowSpan(2)
                ->chartType('bar')
                ->labels($days)
                ->dataset('已发布', $values, '#10b981'),
        ];
    }
}
```

`stat()` 接收原始数字，面板按其语言环境格式化。金额请在卡片上调用 `money()`：`->stat('Revenue', $revenue)->money('USD')` 在英文中显示 `$1,591,285`，在俄文中显示 `1 591 285 $`。`precision()`、`prefix()` 和 `suffix()` 同样作用于最后添加的卡片。

注册：`Admin::screen([ContentDashboardScreen::class])`。URL：
`/admin/dashboard/content`。

## 内置小部件类型

| 类 | `widgetType()` | 用途 |
|---|---|---|
| `StatsOverviewWidget` | `stats` | 单个数值 + 趋势；KPI 卡片。 |
| `ChartWidget` | `chart` | 折线 / 柱状 / 面积 / 雷达（任意数量的 `dataset()` 序列，柱状和面积图可用 `stacked()`）以及环形 / 饼图。 |
| `RecentListWidget` | `recent_list` | Eloquent 模型的最近 N 行。 |
| `MarkdownWidget` | `markdown` | 静态富文本。 |
| `IframeWidget` | `iframe` | 嵌入外部 URL。 |
| `TableWidget` | `table` | 扁平的只读数据。 |
| `HeatmapWidget` | `heatmap` | 矩阵 `rows × cols × value`（例如按小时统计的活跃度）。 |
| `GaugeWidget` | `gauge` | 带阈值的 0..max 单个数值。 |

## 值的格式化

`RecentListWidget` 的列接受 `TableColumn`，格式化方式与资源列表完全相同：
`format()`、`asMoney()`、带 PHP 格式的 `asDate()`/`asDateTime()`、带标签的
`asBadge()`、`asLink()`、`align()`，由同一个单元格渲染器按面板语言环境绘制：

```php
RecentListWidget::make()
    ->model(Order::class)
    ->column('number', 'Number')
    ->column(TableColumn::make('total')->label('Total')->asMoney('USD')->align('right'));
```

图表可通过 `ChartWidget::money('USD')` 将数值显示为金额，或通过 `precision(2)`
固定小数位数。

## 尺寸

每个小部件都有 `size()`（1..12 列，默认 6）和可选的
`rowSpan()`（1..6 行，每行 `140px`，默认值取决于类型：stat=1，
chart/heatmap=2..3）。

```php
ChartWidget::make()->size(8)->rowSpan(2);    // 约半宽，约 296px 高
StatsOverviewWidget::make()->size(3);        // 四分之一宽，默认 rowSpan=1
```

用户可以在编辑模式下覆盖两个方向的尺寸（拖动右下角）。

## 轮询

```php
ChartWidget::make()
    ->title('实时注册')
    ->refresh(30);   // 每 30 秒重新获取一次
```

前端计算可见小部件中最小的 `refresh`，并在每个间隔内轮询一次
`/api/admin/dashboard/widgets?key={slug}&period={p}`。
整个仪表板只有一个定时器。

## 编辑模式

在仪表板工具栏中点击“Edit”。每个小部件上会出现覆盖层：

- **☰** 拖拽手柄——调整顺序
- **⚙** 配置——打开小部件配置对话框（标题/尺寸/类型特有的设置）
- **×** 移除（如果是 manifest 中的小部件则为隐藏——软覆盖）
- **↘** 调整大小——沿两个方向拖动（X=列，Y=行）

用户还可以 **+ Add widget**——打开类型选择对话框。自定义小部件的
slug 为 `slug = "custom.{type}.{timestamp}"`。

保存 → 以完整的小部件数组 POST `/api/admin/dashboard/save`。
按用户持久化到 `admin_dashboard_layouts` 中。

## 按用户覆盖

模型如下：

```
manifest（清单）声明小部件（宿主代码定义规范布局）。
用户布局（DashboardLayout 行）叠加在其上——slug 相同，
{size, position, hidden, rowSpan} 不同；另加用户自定义添加的小部件。
```

如果 manifest 发生变化（代码中新增了小部件），默认情况下它会出现在
用户网格的末尾。

### 同一个类的多个小部件

仪表板按 slug 区分小部件，而小部件的 slug 来自其类——因此两个
`ChartWidget` 本会共用一个 slug。实际上不会：同一 slug 的第二个及之后的
实例会按声明顺序追加 `-2`、`-3`（`chart`、`chart-2`）。第一个保留原始
slug，因此之前保存的布局仍然指向它。

由于后缀取决于声明顺序，当顺序可能变化时，请为各实例命名，这样已保存
的布局就会始终关联到正确的小部件：

```php
public function widgets(): array
{
    return [
        ChartWidget::make()->withSlug('revenue')->title('收入'),
        ChartWidget::make()->withSlug('signups')->title('注册'),
    ];
}
```

## 权限

仪表板及其每个小部件都可以设置访问控制：

```php
final class SalesDashboardScreen extends DashboardScreen
{
    public function permission(): array|string|null
    {
        return 'sales.dashboard';            // 数组表示“需要全部权限”
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

这些规则在服务器端执行，无论仪表板在何处提供：

- 用户无权打开的仪表板不会出现在 manifest 和菜单中，针对它的每个
  `/api/admin/dashboard/*` 调用都返回 `403`；
- 用户无权查看的小部件会在调用其 `data()` 之前被剔除——它的查询根本
  不会执行——在 manifest、`dashboard/widgets`（切换时间段和轮询）以及
  `layout()` 中都是如此；
- 已保存的按用户布局无法把这样的小部件带回来：`dashboard/get`
  和 `dashboard/save` 会将其剥离。

把开销大的工作放在小部件的 `data()` 中。`widgets()` 在构建列表时计算的
任何内容（例如 `->stat('TOTAL', Article::count())`）都会对每个能打开该
仪表板的用户执行，而与小部件自身的权限无关。

## 时间段

仪表板工具栏有一个时间段切换器（7 / 30 / 90 天 / 全部时间）。选中的
时间段会发送到 `/api/admin/dashboard/widgets?key={slug}&period={p}`，
按用户保存，并以 `DashboardContext` 的形式传给每个小部件：

```php
use Dskripchenko\LaravelAdmin\Widget\Widget;

class NewOrdersWidget extends Widget
{
    public function widgetType(): string { return 'stats'; }

    public function data(): array
    {
        $context = $this->dashboardContext();   // ->period, ->days(), ->from(), ->to()

        $count = $context->constrain(Order::query(), 'created_at')->count();

        return ['stats' => [['label' => '新订单', 'value' => $count]]];
    }
}
```

`DashboardContext::constrain($query, $column)` 会添加 `where($column, '>=', from)`，
对于 `all` 则不改动查询。时间段为 `all`，或者是天数后跟 `d`
（`7d`、`14d`、`90d`）。

内置的列表小部件通过 `withinPeriod()` 启用时间段：

```php
RecentListWidget::make()->model(Order::class)->column('number')->withinPeriod();
TableWidget::make()->model(Order::class)->withinPeriod('paid_at');
```

仪表板 Screen 也可以在构建小部件时自行读取时间段——
`$this->period()`、`$this->periodDays()` 或 `$this->dashboardContext()`——
这正是小部件拥有上下文之前仪表板的写法。忽略时间段的小部件可以继续
照常工作。

只有当仪表板上有内容依赖于时间段时，才会显示切换器：在 `data()` 中读取
`dashboardContext()` 的小部件、标记了 `->periodAware()`（或
`withinPeriod()`）的小部件，或者在 `widgets()` 中读取时间段的 Screen。
要显式选择时间段：

```php
public function periods(): ?array
{
    return ['7d', '14d', '30d'];   // [] 隐藏切换器，null = 自动
}

public function defaultPeriod(): string
{
    return '14d';
}
```

## 自定义小部件

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
    return [WeatherWidget::make()->title('天气')->size(3)];
}
```

前端：为该类型注册一个 Vue 组件：

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './WeatherWidget.vue'
registerWidget('weather', WeatherWidget)
```

## 插件小部件

包没有地方声明小部件——仪表板类属于宿主。因此
`$admin->widgets([...])` 用于注册小部件类，当前面板的每个
`DashboardScreen` 都会在自身的小部件之后加载它们：

```php
public function boot(Admin $admin): void
{
    $admin->widgets([QueueDepthWidget::class]);
}
```

无论仪表板在何处提供——manifest、`dashboard/widgets` 刷新以及已保存的
布局——它们都会出现，并遵循与声明的小部件相同的权限规则，因此设置了
`permission()` 的插件小部件只会显示给拥有该权限的用户。小部件通过容器
构建，因此可以在构造函数中请求依赖。重复项按 slug 剔除：如果宿主自己
已经放置了同一个小部件（带有自己的标题或尺寸），就不会再添加第二份。
无法构建的小部件会被跳过，因此损坏的插件绑定不会导致仪表板崩溃。

## 页头指示器

针对同一情形——包有信息要展示却无处可放——的相邻机制：
`$admin->statusIndicators([...])` 以及
`Dskripchenko\LaravelAdmin\Status\StatusIndicator` 接口。面板负责绘制
指示器，插件负责其状态（`key()` 和 `state()`）——见
[system.status](../../ru/api/system.md)（俄文）。

## 另请参阅

- [Screen](screens.md) —— `DashboardScreen` 继承自 `Screen`
- [权限](permissions.md)
- [架构](../architecture.md) —— Widget 的 toArray 结构

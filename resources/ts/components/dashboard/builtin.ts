/**
 * Default-bundle с минимальным набором builtin widget-типов.
 */

import { registerWidgets } from './registry'
import StatWidget from './StatWidget.vue'
import BarChartWidget from './BarChartWidget.vue'
import DonutChartWidget from './DonutChartWidget.vue'
import ChartWidget from './ChartWidget.vue'
import RecentTableWidget from './RecentTableWidget.vue'
import HeatmapWidget from './HeatmapWidget.vue'
import GaugeWidget from './GaugeWidget.vue'
import MarkdownWidget from './MarkdownWidget.vue'
import TableWidget from './TableWidget.vue'
import IframeWidget from './IframeWidget.vue'

/**
 * Регистрирует встроенные widget-компоненты + backend-aliases. Имена
 * слева совпадают с тем что отдают backend Widget::widgetType()
 * (`stats`/`chart`/`recent_list` и т.д.); справа — frontend Vue-компонент.
 */
export function registerBuiltinWidgets(): void {
  registerWidgets({
    // Stat / Stats overview
    stat: StatWidget,
    stats: StatWidget,
    // Charts: универсальный диспетчер по data.type → bar/donut/...
    chart: ChartWidget,
    'bar-chart': BarChartWidget,
    'donut-chart': DonutChartWidget,
    // Recent list (таблица последних записей)
    'recent-table': RecentTableWidget,
    recent_list: RecentTableWidget,
    'recent-list': RecentTableWidget,
    // Heatmap / Gauge / Markdown
    heatmap: HeatmapWidget,
    gauge: GaugeWidget,
    markdown: MarkdownWidget,
    // Полнофункциональная таблица (resource-колонки) и iframe-встройка
    table: TableWidget,
    iframe: IframeWidget,
  })
}

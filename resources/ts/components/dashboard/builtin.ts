/**
 * Default-bundle с минимальным набором builtin widget-типов.
 */

import { registerWidgets } from './registry'
import StatWidget from './StatWidget.vue'
import BarChartWidget from './BarChartWidget.vue'
import DonutChartWidget from './DonutChartWidget.vue'
import RecentTableWidget from './RecentTableWidget.vue'
import HeatmapWidget from './HeatmapWidget.vue'
import GaugeWidget from './GaugeWidget.vue'
import MarkdownWidget from './MarkdownWidget.vue'

export function registerBuiltinWidgets(): void {
  registerWidgets({
    stat: StatWidget,
    'bar-chart': BarChartWidget,
    'donut-chart': DonutChartWidget,
    'recent-table': RecentTableWidget,
    heatmap: HeatmapWidget,
    gauge: GaugeWidget,
    markdown: MarkdownWidget,
  })
}

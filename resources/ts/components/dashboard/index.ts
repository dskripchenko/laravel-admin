/**
 * Public exports dashboard-системы.
 */

export { default as DashboardPage } from './DashboardPage.vue'
export { default as WidgetRenderer } from './WidgetRenderer.vue'
export type { WidgetNode } from './WidgetRenderer.vue'

export { default as StatWidget } from './StatWidget.vue'
export { default as BarChartWidget } from './BarChartWidget.vue'
export { default as DonutChartWidget } from './DonutChartWidget.vue'
export { default as RecentTableWidget } from './RecentTableWidget.vue'
export { default as HeatmapWidget } from './HeatmapWidget.vue'
export { default as GaugeWidget } from './GaugeWidget.vue'
export { default as MarkdownWidget } from './MarkdownWidget.vue'
export { default as UnknownWidget } from './UnknownWidget.vue'

export {
  registerWidget,
  registerWidgets,
  getWidget,
  hasWidget,
  listWidgets,
  clearWidgetRegistry,
} from './registry'

export { registerBuiltinWidgets } from './builtin'

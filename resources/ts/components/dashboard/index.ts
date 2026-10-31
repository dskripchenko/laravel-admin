/**
 * The public exports of the dashboard system.
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
export { default as DiagramWidget } from './DiagramWidget.vue'
export { default as BpmnWidget } from './BpmnWidget.vue'
export { default as KanbanWidget } from './KanbanWidget.vue'
export { default as CalendarWidget } from './CalendarWidget.vue'
export { default as WidgetFrame } from './WidgetFrame.vue'
export { default as WidgetConfigDialog } from './WidgetConfigDialog.vue'
export { default as WidgetConfigForm } from './WidgetConfigForm.vue'

export {
  registerWidget,
  registerWidgets,
  getWidget,
  hasWidget,
  listWidgets,
  clearWidgetRegistry,
  describeWidget,
  getWidgetDescription,
  hasWidgetDescription,
  listAddableWidgets,
} from './registry'

export type {
  WidgetTypeDescription,
  WidgetConfigField,
  WidgetConfigOption,
  WidgetConfigValues,
  WidgetConfigContext,
  WidgetBuildContext,
  WidgetText,
} from './widgetConfig'
export { buildWidgetConfig, initialValues, resourcesWithView } from './widgetConfig'
export { builtinWidgetDescriptions } from './builtinDescriptions'

export { registerBuiltinWidgets } from './builtin'

/**
 * The default bundle, with the minimal set of built-in widget types.
 */

import { describeWidget, hasWidget, hasWidgetDescription, registerWidget } from './registry'
import { builtinWidgetDescriptions } from './builtinDescriptions'
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
import DiagramWidget from './DiagramWidget.vue'
import BpmnWidget from './BpmnWidget.vue'
import KanbanWidget from './KanbanWidget.vue'
import CalendarWidget from './CalendarWidget.vue'

/**
 * Registers the built-in widget components and the backend's aliases. The
 * names on the left match what the backend's Widget::widgetType() returns —
 * `stats`, `chart`, `recent_list` and so on — and on the right stands the Vue
 * component. Each type a user may add also gets its description for the
 * add and configure dialogs (builtinDescriptions.ts).
 */
export function registerBuiltinWidgets(): void {
  // We do not override what the host registered before createAdminApp().
  const registerAbsent = (bundle: Record<string, unknown>): void => {
    for (const [k, v] of Object.entries(bundle)) {
      if (!hasWidget(k)) registerWidget(k, v as never)
    }
  }
  registerAbsent({
    // Stat / Stats overview
    stat: StatWidget,
    stats: StatWidget,
    // Charts: one dispatcher over data.type → bar, donut and the rest
    chart: ChartWidget,
    'bar-chart': BarChartWidget,
    'donut-chart': DonutChartWidget,
    // The recent list — a table of the latest records
    'recent-table': RecentTableWidget,
    recent_list: RecentTableWidget,
    'recent-list': RecentTableWidget,
    // Heatmap / Gauge / Markdown
    heatmap: HeatmapWidget,
    gauge: GaugeWidget,
    markdown: MarkdownWidget,
    // The full table, with resource columns, and the iframe embed
    table: TableWidget,
    iframe: IframeWidget,
    // Documents: a diagram in any notation, a BPMN/DMN model, and a kanban
    // board or a calendar — of a resource or written as markup
    diagram: DiagramWidget,
    bpmn: BpmnWidget,
    kanban: KanbanWidget,
    calendar: CalendarWidget,
  })
  // The add / configure dialog descriptions, likewise without replacing the host's.
  for (const [type, description] of Object.entries(builtinWidgetDescriptions())) {
    if (!hasWidgetDescription(type)) describeWidget(type, description)
  }
}

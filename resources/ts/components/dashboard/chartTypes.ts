/**
 * The chart types ChartWidget draws, and the component each one goes to.
 *
 * The backend's ChartWidget::ALLOWED_TYPES lists what a host may ask for;
 * backendParity.test.ts checks every one of them has an entry here.
 */
export type ChartRenderer = 'bar' | 'line' | 'area' | 'pie' | 'donut' | 'radar'

export const CHART_RENDERERS: Readonly<Record<string, ChartRenderer>> = {
  bar: 'bar',
  line: 'line',
  area: 'area',
  pie: 'pie',
  doughnut: 'donut',
  radar: 'radar',
}

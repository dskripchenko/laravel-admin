/**
 * The chart types ChartWidget draws, and the component each one goes to.
 *
 * The backend's ChartWidget::ALLOWED_TYPES lists what a host may ask for;
 * backendParity.test.ts checks every one of them has an entry here.
 */
export const CHART_RENDERERS: Readonly<Record<string, 'bar' | 'donut'>> = {
  bar: 'bar',
  line: 'bar',
  area: 'bar',
  pie: 'donut',
  doughnut: 'donut',
}

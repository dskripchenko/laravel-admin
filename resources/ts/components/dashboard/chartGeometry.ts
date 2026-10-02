/**
 * Shared, dependency-free helpers of the SVG charts: the series palette, the
 * normalization of the backend's datasets, nice axis ticks and number
 * formatting. Pure functions — the components only lay the results out.
 */
import { toneColor } from './toneColor'

/** One drawable series: the backend's dataset with its colour resolved. */
export interface ChartSeries {
  label: string
  /** A non-finite entry (null, NaN) is a gap: lines break, bars are skipped. */
  data: Array<number | null>
  color: string
}

/** The backend's dataset, as ChartWidget::data() sends it. */
export interface RawDataset {
  label?: string
  data?: unknown[]
  color?: string
}

/**
 * The categorical order for several series. It is fixed — a series keeps its
 * colour whatever else is on the chart — and validated for colour-vision
 * deficiency on both the light and the dark surface (blue, amber, green,
 * violet, pink, cyan). Each slot can be overridden through a
 * `--admin-chart-series-N` custom property.
 */
export const SERIES_PALETTE: readonly string[] = [
  'var(--admin-chart-series-1, #2563eb)',
  'var(--admin-chart-series-2, #d97706)',
  'var(--admin-chart-series-3, #059669)',
  'var(--admin-chart-series-4, #9333ea)',
  'var(--admin-chart-series-5, #db2777)',
  'var(--admin-chart-series-6, #0891b2)',
]

/** The colour of a lone series: the theme's accent, as the bar chart always had. */
export const SINGLE_SERIES_COLOR = 'var(--uid-accent)'

function toNumber(v: unknown): number | null {
  if (typeof v === 'number') return Number.isFinite(v) ? v : null
  if (typeof v === 'string' && v.trim() !== '') {
    const n = Number(v)
    return Number.isFinite(n) ? n : null
  }
  return null
}

/**
 * Turns the backend's datasets into drawable series. An explicit `color` — a
 * tone name or a CSS colour, see toneColor — wins;
 * otherwise a lone series takes `singleColor` and several take the palette in
 * order.
 */
export function toSeries(
  datasets: RawDataset[] | undefined | null,
  singleColor: string = SINGLE_SERIES_COLOR,
): ChartSeries[] {
  const list = Array.isArray(datasets) ? datasets.filter((d) => d && typeof d === 'object') : []
  return list.map((ds, i) => ({
    label: typeof ds.label === 'string' && ds.label !== '' ? ds.label : `#${i + 1}`,
    data: Array.isArray(ds.data) ? ds.data.map(toNumber) : [],
    color:
      toneColor(ds.color) !== ''
        ? toneColor(ds.color)
        : list.length === 1
          ? singleColor
          : SERIES_PALETTE[i % SERIES_PALETTE.length],
  }))
}

/**
 * The category labels: the backend's `labels`, padded with 1-based indices
 * when a dataset is longer than them.
 */
export function resolveLabels(labels: unknown[] | undefined | null, series: ChartSeries[]): string[] {
  const given = Array.isArray(labels) ? labels.map((l) => String(l ?? '')) : []
  const n = Math.max(given.length, ...series.map((s) => s.data.length), 0)
  const out: string[] = []
  for (let i = 0; i < n; i++) out.push(given[i] ?? String(i + 1))
  return out
}

/** A chart is empty when no series carries a single non-zero value. */
export function isEmptySeries(series: ChartSeries[]): boolean {
  return !series.some((s) => s.data.some((v) => v !== null && v !== 0))
}

/**
 * Cumulative sums per category, for stacked charts: result[s][i] is the top of
 * series s at category i. Gaps count as zero.
 */
export function stackSeries(series: ChartSeries[], n: number): number[][] {
  const acc = new Array<number>(n).fill(0)
  return series.map((s) =>
    acc.map((_, i) => {
      acc[i] += s.data[i] ?? 0
      return acc[i]
    }),
  )
}

export interface NiceScale {
  min: number
  max: number
  ticks: number[]
}

function niceStep(range: number, count: number): number {
  const raw = range / Math.max(1, count)
  const pow = Math.pow(10, Math.floor(Math.log10(raw)))
  const frac = raw / pow
  const nice = frac <= 1 ? 1 : frac <= 2 ? 2 : frac <= 2.5 ? 2.5 : frac <= 5 ? 5 : 10
  return nice * pow
}

/**
 * A "nice" linear scale over [min, max] with about `count` intervals. Zero is
 * always inside, so bars and areas grow from a real baseline.
 */
export function niceScale(min: number, max: number, count = 4): NiceScale {
  let lo = Math.min(0, min)
  let hi = Math.max(0, max)
  if (lo === hi) hi = lo + 1
  const step = niceStep(hi - lo, count)
  lo = Math.floor(lo / step) * step
  hi = Math.ceil(hi / step) * step
  const ticks: number[] = []
  // Rounding keeps 0.1 + 0.2 from turning into a 0.30000000000000004 label.
  for (let v = lo; v <= hi + step / 2; v += step) ticks.push(Math.round(v / step) * step)
  return { min: lo, max: hi, ticks }
}

const compactFormat = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })
const fullFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 })

/** An axis tick: compact (1.2K, 3M). */
export function formatTick(v: number): string {
  return compactFormat.format(v)
}

/** A value in a tooltip or the data table: full, with grouping. */
export function formatValue(v: number | null): string {
  return v === null ? '—' : fullFormat.format(v)
}

/** A rough text width in px for the axis font — enough to keep labels apart. */
export function estimateTextWidth(text: string, fontPx = 11): number {
  return text.length * fontPx * 0.6
}

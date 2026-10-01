<script setup lang="ts">
/**
 * ChartWidget — the dispatcher over `data.chartType`: line, bar, area, pie,
 * doughnut and radar.
 *
 * The backend's ChartWidget::data() returns `{chartType, labels, datasets,
 * stacked}`. This wrapper resolves the series (colours included) and leaves
 * the drawing to the specialized components: CartesianChartWidget for line,
 * area and bar — every dataset becomes a series — RadarChartWidget for radar,
 * and DonutChartWidget for pie and doughnut, which slice the first dataset.
 */
import { computed } from 'vue'
import CartesianChartWidget from './CartesianChartWidget.vue'
import DonutChartWidget from './DonutChartWidget.vue'
import RadarChartWidget from './RadarChartWidget.vue'
import UnknownWidget from './UnknownWidget.vue'
import { CHART_RENDERERS } from './chartTypes'
import { resolveLabels, toSeries, type RawDataset } from './chartGeometry'

interface ChartData {
  /**
   * The backend's ChartWidget::data() returns `chartType`. The older wrapper
   * read `type`, which stays as a fallback for compatibility.
   */
  chartType?: string
  type?: string
  labels?: Array<string | number>
  datasets?: RawDataset[]
  stacked?: boolean
}

interface Props {
  type?: string
  title?: string
  description?: string
  size?: number
  data?: ChartData
  /*
   * WidgetRenderer also spreads `data` flat. Declared here so they do not
   * fall through onto the rendered chart, and used when `data` is absent.
   */
  chartType?: string
  labels?: Array<string | number>
  datasets?: RawDataset[]
  stacked?: boolean
}
const props = defineProps<Props>()

const source = computed<ChartData>(() => props.data ?? {
  chartType: props.chartType,
  labels: props.labels,
  datasets: props.datasets,
  stacked: props.stacked,
})

const resolvedType = computed<string>(
  () => source.value.chartType ?? source.value.type ?? 'bar',
)

const renderer = computed(() => CHART_RENDERERS[resolvedType.value])

/**
 * The palette of a donut or a pie, one colour per slice. When the backend
 * sends a `color` in the dataset, that wins.
 */
const DEFAULT_PALETTE = [
  '#10b981', // teal-500
  '#f59e0b', // amber-500
  '#9ca3af', // gray-400
  '#3b82f6', // blue-500
  '#dc2626', // red-600
  '#a855f7', // purple-500
  '#ec4899', // pink-500
]

const chartSeries = computed(() => toSeries(source.value.datasets))
const chartLabels = computed(() => resolveLabels(source.value.labels, chartSeries.value))
const isStacked = computed(() => source.value.stacked === true)

/** In a donut or a pie each item gets its share of the total. */
const donutData = computed(() => {
  const ds = source.value.datasets?.[0]
  if (!ds || !Array.isArray(ds.data)) return []
  return ds.data.map((v, i) => ({
    label: String(source.value.labels?.[i] ?? i + 1),
    value: Number(v) || 0,
    color: ds.color ?? DEFAULT_PALETTE[i % DEFAULT_PALETTE.length],
  }))
})
</script>

<template>
  <DonutChartWidget
    v-if="renderer === 'donut'"
    :title="title"
    :data="donutData"
  />
  <RadarChartWidget
    v-else-if="renderer === 'radar'"
    :title="title"
    :description="description"
    :labels="chartLabels"
    :series="chartSeries"
  />
  <CartesianChartWidget
    v-else-if="renderer === 'bar' || renderer === 'line' || renderer === 'area'"
    :kind="renderer"
    :title="title"
    :description="description"
    :labels="chartLabels"
    :series="chartSeries"
    :stacked="isStacked"
  />
  <UnknownWidget v-else :type="`chart:${resolvedType}`" />
</template>

<script setup lang="ts">
/**
 * ChartWidget — the dispatcher over `data.type`: bar, line, pie, doughnut,
 * area.
 *
 * The backend's ChartWidget::data() returns `{type, labels, datasets, ...}`.
 * This frontend wrapper turns that into plain arrays of data points or slices
 * and leaves the drawing to the specialized components — Bar, Donut and the
 * rest.
 */
import { computed } from 'vue'
import BarChartWidget from './BarChartWidget.vue'
import DonutChartWidget from './DonutChartWidget.vue'
import UnknownWidget from './UnknownWidget.vue'

interface ChartDataset {
  label: string
  data: number[]
  color?: string
}
interface ChartData {
  /**
   * The backend's ChartWidget::data() returns `chartType`. The older wrapper
   * read `type`, which stays as a fallback for compatibility.
   */
  chartType?: string
  type?: string
  labels?: string[]
  datasets?: ChartDataset[]
}

interface Props {
  type?: string
  title?: string
  size?: number
  data?: ChartData
}
const props = defineProps<Props>()

const chartType = computed<string>(
  () => props.data?.chartType ?? props.data?.type ?? 'bar',
)

/**
 * The default palette of a donut or a pie. It comes from the --uid-* tokens,
 * falling back to static colours. When the backend sends a `color` in the
 * dataset, that wins.
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

/**
 * The bar, line and area charts expect a list of {label, value}. We take the
 * first dataset; stacking several is for a later round.
 */
const barData = computed(() => {
  const ds = props.data?.datasets?.[0]
  if (!ds) return []
  return ds.data.map((v, i) => ({
    label: props.data?.labels?.[i] ?? String(i + 1),
    value: v,
  }))
})

const barAccent = computed<string | undefined>(
  () => props.data?.datasets?.[0]?.color,
)

/** In a donut or a pie each item gets its share of the total. */
const donutData = computed(() => {
  const ds = props.data?.datasets?.[0]
  if (!ds) return []
  return ds.data.map((v, i) => ({
    label: props.data?.labels?.[i] ?? String(i + 1),
    value: v,
    color: ds.color ?? DEFAULT_PALETTE[i % DEFAULT_PALETTE.length],
  }))
})
</script>

<template>
  <DonutChartWidget
    v-if="chartType === 'doughnut' || chartType === 'pie'"
    :title="title"
    :data="donutData"
  />
  <BarChartWidget
    v-else-if="chartType === 'bar' || chartType === 'line' || chartType === 'area'"
    :title="title"
    :data="barData"
    :accent="barAccent"
  />
  <UnknownWidget v-else :type="`chart:${chartType}`" />
</template>

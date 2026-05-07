<script setup lang="ts">
/**
 * ChartWidget — диспетчер по `data.type` (bar / line / pie / doughnut / area).
 *
 * Backend ChartWidget::data() отдаёт `{type, labels, datasets, ...}`. Эта
 * frontend-обёртка трансформирует структуру в plain Datum/Slice массивы
 * и делегирует рисование специализированным компонентам (Bar/Donut/...).
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

const chartType = computed<string>(() => props.data?.type ?? 'bar')

/**
 * Палитра по умолчанию для donut/pie. Берём из --uid-* токенов, fallback —
 * статические цвета. Backend может прислать `color` в dataset — тогда
 * предпочтение ему.
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
 * Bar/line/area-чарты ожидают список {label, value}.
 * Берём первый dataset (multi-dataset stacked будет реализован в следующей
 * итерации).
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

/** Donut/pie — каждый item получает свою долю общего total. */
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

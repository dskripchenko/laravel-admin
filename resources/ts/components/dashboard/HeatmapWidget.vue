<script setup lang="ts">
/**
 * HeatmapWidget — a matrix heatmap of rows × cols.
 *
 * The backend's HeatmapWidget::data() gives {rows: ['Mon',...], cols:
 * ['May',...], matrix: [[...], ...], colorScale, min, max, format}, where
 * matrix[r][c] is a number or null ("no data" — an empty outlined cell, unlike
 * 0). It is drawn by the kit's UidHeatmapMatrix: every column labelled, the
 * colour scale, a "row × column: value" tooltip and a min → max legend.
 */
import { computed } from 'vue'
import { UidCard, UidHeatmapMatrix } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'
import { formatValue as formatChartValue, type ChartValueFormat } from './chartGeometry'

interface Props {
  title?: string
  rows?: string[]
  cols?: string[]
  matrix?: (number | null)[][]
  /** A scale name ('default', 'viridis', 'magma', 'blues'…), a CSS colour or custom stops. */
  colorScale?: string | string[]
  /** The colour domain; the matrix's own min/max when null. */
  min?: number | null
  max?: number | null
  /** How a value is shown — HeatmapWidget::money() / precision(). */
  format?: ChartValueFormat | null
  /** A custom formatter, for a heatmap mounted from code. */
  formatValue?: (v: number) => string
  /** A single CSS colour; kept for heatmaps mounted from code, colorScale wins. */
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  rows: () => [],
  cols: () => [],
  matrix: () => [],
  colorScale: undefined,
  min: null,
  max: null,
  format: null,
  formatValue: undefined,
  color: undefined,
})

const scale = computed<string | string[]>(() => props.colorScale ?? props.color ?? 'default')

const formatter = computed<(v: number) => string>(() =>
  props.formatValue ?? ((v: number) => formatChartValue(v, props.format)),
)

const hasData = computed(() => props.rows.length > 0 && props.cols.length > 0 && props.matrix.length > 0)
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-heatmap-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidHeatmapMatrix
      v-if="hasData"
      class="admin-heatmap"
      :rows="rows"
      :cols="cols"
      :values="matrix"
      :color-scale="scale"
      :min="min ?? undefined"
      :max="max ?? undefined"
      :format-value="formatter"
      :aria-label="title || undefined"
    />
    <div v-else class="admin-heatmap__empty">{{ tr('Нет данных') }}</div>
  </UidCard>
</template>

<style>
.admin-heatmap__empty {
  padding: var(--uid-space-md);
  text-align: center;
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-sm);
}
</style>

<script setup lang="ts">
/**
 * HeatmapWidget — матричный heatmap rows × cols.
 *
 * Backend HeatmapWidget::toArray() даёт {rows: ['Пн',...], cols: ['00h',...],
 * matrix: [[..24..], [..24..], ...]}. Каждая ячейка matrix[r][c] — число.
 *
 * Используем собственный CSS-grid вместо UidHeatmap (которая календарная,
 * data=[{date,value}] — другой use-case). Цвет по нормированной шкале
 * 0..max: opacity = value/max, фоновый цвет — accent.
 */
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'

interface Props {
  title?: string
  rows?: string[]
  cols?: string[]
  matrix?: number[][]
  /** Для подписи в hover'e — формат значения. */
  formatValue?: (v: number, row: string, col: string) => string
  /** CSS-цвет ячейки. Default — accent token. */
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  rows: () => [],
  cols: () => [],
  matrix: () => [],
  formatValue: undefined,
  color: 'var(--uid-color-primary, #14b8a6)',
})

const maxValue = computed(() => {
  let max = 0
  for (const row of props.matrix) {
    for (const v of row) {
      if (typeof v === 'number' && v > max) max = v
    }
  }
  return max || 1
})

function cellOpacity(v: number): number {
  if (typeof v !== 'number' || v <= 0) return 0
  return Math.max(0.1, Math.min(1, v / maxValue.value))
}

function cellTitle(value: number, rowIdx: number, colIdx: number): string {
  const r = props.rows[rowIdx] ?? ''
  const c = props.cols[colIdx] ?? ''
  if (props.formatValue) return props.formatValue(value, r, c)
  return `${r} ${c}: ${value}`
}
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-heatmap-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div
      v-if="rows.length > 0 && cols.length > 0 && matrix.length > 0"
      class="admin-heatmap"
      :style="{ '--admin-heatmap-cols': cols.length }"
    >
      <div class="admin-heatmap__cols-axis" aria-hidden="true">
        <span
          v-for="(col, ci) in cols"
          :key="`c-${ci}`"
          class="admin-heatmap__col-label"
        >{{ ci % 3 === 0 ? col : '' }}</span>
      </div>
      <div
        v-for="(rowValues, ri) in matrix"
        :key="`r-${ri}`"
        class="admin-heatmap__row"
      >
        <span class="admin-heatmap__row-label">{{ rows[ri] ?? '' }}</span>
        <div class="admin-heatmap__cells">
          <span
            v-for="(v, ci) in rowValues"
            :key="`cell-${ri}-${ci}`"
            class="admin-heatmap__cell"
            :style="{ background: color, opacity: cellOpacity(v) }"
            :title="cellTitle(v, ri, ci)"
          />
        </div>
      </div>
    </div>
    <div v-else class="admin-heatmap__empty">Нет данных</div>
  </UidCard>
</template>

<style>
.admin-heatmap {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: var(--uid-font-size-xs, 11px);
}
.admin-heatmap__cols-axis {
  display: grid;
  grid-template-columns: 32px repeat(var(--admin-heatmap-cols), 1fr);
  gap: 2px;
  color: var(--uid-text-tertiary, #9ca3af);
  margin-bottom: 4px;
}
.admin-heatmap__cols-axis::before { content: ''; }
.admin-heatmap__col-label {
  font-size: 10px;
  text-align: center;
  white-space: nowrap;
}
.admin-heatmap__row {
  display: grid;
  grid-template-columns: 32px 1fr;
  gap: 6px;
  align-items: center;
}
.admin-heatmap__row-label {
  color: var(--uid-text-secondary, #6b7280);
  font-size: 11px;
  text-align: right;
}
.admin-heatmap__cells {
  display: grid;
  grid-template-columns: repeat(var(--admin-heatmap-cols), 1fr);
  gap: 2px;
}
.admin-heatmap__cell {
  display: block;
  width: 100%;
  height: 14px;
  border-radius: 2px;
  background: var(--uid-color-primary, #14b8a6);
  opacity: 0;
  transition: transform 80ms ease;
}
.admin-heatmap__cell:hover {
  transform: scale(1.4);
  z-index: 1;
}
.admin-heatmap__empty {
  padding: var(--uid-space-md);
  text-align: center;
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-sm);
}
</style>

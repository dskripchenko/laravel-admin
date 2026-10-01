<script setup lang="ts">
/**
 * ChartTooltip — the hover card of a chart: the category as its heading and
 * one row per series. Positioned inside the chart's box, flipping to the left
 * of the cursor near the right edge so it never leaves the widget.
 */
import { computed } from 'vue'

interface Row {
  label: string
  color: string
  value: string
}

interface Props {
  x: number
  y: number
  title: string
  rows: Row[]
  /** The chart box width, to keep the card inside it. */
  boundsWidth: number
}

const props = defineProps<Props>()

const style = computed(() => {
  const flip = props.x > props.boundsWidth / 2
  return {
    top: `${Math.max(0, props.y)}px`,
    ...(flip
      ? { right: `${Math.max(0, props.boundsWidth - props.x + 12)}px` }
      : { left: `${props.x + 12}px` }),
  }
})
</script>

<template>
  <div class="admin-chart-tooltip" role="presentation" :style="style">
    <div class="admin-chart-tooltip__title">{{ title }}</div>
    <div v-for="(row, idx) in rows" :key="idx" class="admin-chart-tooltip__row">
      <span class="admin-chart-tooltip__swatch" :style="{ background: row.color }" />
      <span class="admin-chart-tooltip__label">{{ row.label }}</span>
      <span class="admin-chart-tooltip__value">{{ row.value }}</span>
    </div>
  </div>
</template>

<style>
.admin-chart-tooltip {
  position: absolute;
  z-index: 2;
  pointer-events: none;
  min-width: 120px;
  max-width: 260px;
  padding: var(--uid-space-xs) var(--uid-space-sm);
  background: var(--uid-surface-overlay, var(--uid-surface-raised));
  border: 1px solid var(--uid-border-default);
  border-radius: var(--uid-radius-md);
  box-shadow: var(--uid-shadow-md);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-primary);
}
.admin-chart-tooltip__title {
  margin-bottom: var(--uid-space-2xs);
  color: var(--uid-text-secondary);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-chart-tooltip__row {
  display: flex;
  align-items: center;
  gap: var(--uid-space-xs);
  line-height: 1.6;
}
.admin-chart-tooltip__swatch {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  flex: none;
}
.admin-chart-tooltip__label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--uid-text-secondary);
}
.admin-chart-tooltip__value {
  font-variant-numeric: tabular-nums;
}
</style>

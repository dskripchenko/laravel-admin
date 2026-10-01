<script setup lang="ts">
/**
 * ChartLegend — the series key under a multi-series chart. The text stays in
 * the text tokens; the swatch beside it carries the series colour, so identity
 * never rests on colour alone.
 */
interface Item {
  label: string
  color: string
}

defineProps<{ items: Item[] }>()
</script>

<template>
  <ul class="admin-chart-legend">
    <li v-for="(item, idx) in items" :key="idx" class="admin-chart-legend__item">
      <span class="admin-chart-legend__swatch" :style="{ background: item.color }" />
      <span class="admin-chart-legend__label">{{ item.label }}</span>
    </li>
  </ul>
</template>

<style>
.admin-chart-legend {
  list-style: none;
  margin: var(--uid-space-xs) 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-2xs) var(--uid-space-md);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
}
.admin-chart-legend__item {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-2xs);
  min-width: 0;
}
.admin-chart-legend__swatch {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  flex: none;
}
.admin-chart-legend__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

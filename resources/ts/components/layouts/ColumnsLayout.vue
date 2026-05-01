<script setup lang="ts">
/**
 * Columns: 12-колоночный grid. Каждый item.span (1..12) определяет ширину;
 * default — 12/items.length.
 */
import { computed } from 'vue'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  gap?: number | string
  /** Сколько колонок в гриде (default 12). */
  cols?: number
}
const props = withDefaults(defineProps<Props>(), { gap: 16, cols: 12 })

const defaultSpan = computed(() => {
  if (props.items.length === 0) return props.cols
  return Math.max(1, Math.floor(props.cols / props.items.length))
})

function spanFor(item: LayoutNode): number {
  const s = (item as Record<string, unknown>).span
  if (typeof s === 'number' && s > 0) return Math.min(s, props.cols)
  return defaultSpan.value
}
</script>

<template>
  <div
    class="admin-layout-columns"
    :style="{
      gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`,
      gap: typeof gap === 'number' ? `${gap}px` : gap,
    }"
  >
    <div
      v-for="(child, idx) in items"
      :key="idx"
      class="admin-layout-columns__item"
      :style="{ gridColumn: `span ${spanFor(child)}` }"
    >
      <LayoutRenderer :node="child" />
    </div>
  </div>
</template>

<style>
.admin-layout-columns {
  display: grid;
}
</style>

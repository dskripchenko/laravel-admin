<script setup lang="ts">
/**
 * Columns: 12-колоночный grid поверх UidGrid из @dskripchenko/ui.
 * Каждый item.span (1..cols) задаёт ширину; default равные доли.
 */
import { computed } from 'vue'
import { UidGrid } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  /** Зазор между ячейками (CSS, token или px). */
  gap?: string
  /** Сколько колонок в гриде (default 12). */
  cols?: number
}

const props = withDefaults(defineProps<Props>(), {
  gap: 'var(--uid-space-md)',
  cols: 12,
})

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
  <UidGrid :cols="cols" :gap="gap">
    <div
      v-for="(child, idx) in items"
      :key="idx"
      class="admin-columns__item"
      :style="{ gridColumn: `span ${spanFor(child)} / span ${spanFor(child)}` }"
    >
      <LayoutRenderer :node="child" />
    </div>
  </UidGrid>
</template>

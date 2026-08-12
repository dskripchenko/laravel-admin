<script setup lang="ts">
/**
 * Columns: a twelve-column grid over UidGrid from @dskripchenko/ui.
 * Each item.span (1..cols) sets its width; by default the shares are equal.
 */
import { computed } from 'vue'
import { UidGrid } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  /** The gap between the cells: CSS, a token or pixels. */
  gap?: string
  /** How many columns the grid has; 12 by default. */
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

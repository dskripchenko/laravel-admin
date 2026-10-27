<script setup lang="ts">
/**
 * Columns: a twelve-column grid over UidGrid from @dskripchenko/ui.
 * Each item.span (1..cols) sets its width; by default the shares are equal.
 * `ratios` from Columns::ratios() — numbers as shares, strings as CSS track
 * sizes (`minmax(0, 2fr)`, `280px`) — take precedence; on a narrow screen
 * the columns stack.
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
  /** One track per item: a number is a share (`2` → `2fr`), a string is CSS. */
  ratios?: Array<number | string>
}

const props = withDefaults(defineProps<Props>(), {
  gap: 'var(--uid-space-md)',
  cols: 12,
})

const defaultSpan = computed(() => {
  if (props.items.length === 0) return props.cols
  return Math.max(1, Math.floor(props.cols / props.items.length))
})

const template = computed(() => {
  const ratios = props.ratios ?? []
  if (ratios.length === 0 || ratios.length !== props.items.length) return null
  return ratios.map((r) => (typeof r === 'number' ? `minmax(0, ${r}fr)` : r)).join(' ')
})

function spanFor(item: LayoutNode): number {
  const s = (item as Record<string, unknown>).span
  if (typeof s === 'number' && s > 0) return Math.min(s, props.cols)
  return defaultSpan.value
}
</script>

<template>
  <div
    v-if="template"
    class="admin-columns admin-columns--ratios"
    :style="{ gridTemplateColumns: template, gap }"
  >
    <div v-for="(child, idx) in items" :key="idx" class="admin-columns__item">
      <LayoutRenderer :node="child" />
    </div>
  </div>
  <UidGrid v-else :cols="cols" :gap="gap">
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

<style scoped>
.admin-columns--ratios {
  display: grid;
  align-items: start;
}

@media (max-width: 900px) {
  .admin-columns--ratios {
    grid-template-columns: minmax(0, 1fr) !important;
  }
}
</style>

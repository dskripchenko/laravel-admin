<script setup lang="ts">
/**
 * Dashboard layout — widgets embedded into an ORDINARY screen.
 *
 * Backend `Layout\Dashboard::make([...])` is public API, but until now the only
 * thing that could draw it was the dashboard route, which has its own page
 * component and never goes through the layout registry. Put the very same
 * layout on a regular screen and the screen came out empty: heading present,
 * body gone, no error anywhere. Silence is the worst failure mode here — a
 * `layout()` entry that renders nothing looks exactly like a backend that
 * returned nothing.
 *
 * This is deliberately the plain version: a 12-column grid and nothing else.
 * Drag, resize and per-user persistence stay with DashboardPage, because they
 * only mean something where a dashboard can be customised and saved.
 */
import { UidGrid } from '@dskripchenko/ui'
import WidgetRenderer from '../dashboard/WidgetRenderer.vue'
import type { WidgetNode } from '../dashboard/WidgetRenderer.vue'

interface Props {
  items?: WidgetNode[]
  /** Column count of the grid; the backend `gridColumns()` maps here. */
  gridColumns?: number
  gap?: string
}
const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  gridColumns: 12,
  gap: 'var(--uid-space-md)',
})

/**
 * `size` is the span, same convention as DashboardPage — full width when unset.
 * Clamped to the grid: a widget declaring 12 inside a 6-column grid would
 * otherwise silently overflow the row.
 */
function spanOf(w: WidgetNode): number {
  const raw = (w as Record<string, unknown>).size ?? (w as Record<string, unknown>).span
  const v = Number(raw)
  const span = Number.isFinite(v) && v >= 1 ? v : props.gridColumns

  return Math.min(span, props.gridColumns)
}
</script>

<template>
  <UidGrid
    :cols="gridColumns"
    :col-gap="gap"
    row-gap="var(--uid-space-lg)"
  >
    <div
      v-for="(widget, idx) in items"
      :key="idx"
      class="admin-dashboard-layout__cell"
      :style="{ gridColumn: `span ${spanOf(widget)}` }"
    >
      <WidgetRenderer :node="widget" />
    </div>
  </UidGrid>
</template>

<script setup lang="ts">
/**
 * Rows-layout — UidStack column.
 *
 * Если хотя бы один child имеет `span` (1..12), переключается в grid-12
 * mode и каждый child получает `grid-column: span N` (default 12 = full-row).
 * Это позволяет Resource без отдельного Columns/Grid layout'а компактно
 * группировать поля в одной строке (например: slug + version по 6 col).
 */
import { computed } from 'vue'
import { UidStack, UidGrid } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  /** CSS-зазор между элементами (token либо px), по умолчанию --uid-space-md. */
  gap?: string
}
const props = withDefaults(defineProps<Props>(), { gap: 'var(--uid-space-md)' })

function spanOf(node: LayoutNode): number {
  const n = node as Record<string, unknown>
  const raw = n.span ?? (n.attributes && (n.attributes as Record<string, unknown>).span)
  const v = Number(raw)
  return Number.isFinite(v) && v >= 1 && v <= 12 ? v : 0
}

const hasSpan = computed(() => props.items.some((c) => spanOf(c) > 0))
</script>

<template>
  <UidGrid
    v-if="hasSpan"
    :cols="12"
    :col-gap="gap"
    row-gap="var(--uid-space-lg)"
  >
    <div
      v-for="(child, idx) in items"
      :key="idx"
      :style="{ gridColumn: `span ${spanOf(child) || 12}` }"
    >
      <LayoutRenderer :node="child" />
    </div>
  </UidGrid>
  <UidStack
    v-else
    direction="column"
    :gap="gap"
    align="stretch"
  >
    <LayoutRenderer
      v-for="(child, idx) in items"
      :key="idx"
      :node="child"
    />
  </UidStack>
</template>

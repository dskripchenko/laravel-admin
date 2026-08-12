<script setup lang="ts">
/**
 * The rows layout — a UidStack column.
 *
 * As soon as one child carries a `span` (1..12) it switches into the
 * twelve-column grid mode, and every child gets a `grid-column: span N`, the
 * default 12 meaning a full row. That lets a resource group fields compactly
 * into one line — a slug and a version at six columns each — without a
 * separate Columns or Grid layout.
 */
import { computed } from 'vue'
import { UidStack, UidGrid } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  /** The CSS gap between the items, a token or pixels; --uid-space-md by default. */
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

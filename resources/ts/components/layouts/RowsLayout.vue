<script setup lang="ts">
/**
 * Rows-layout — UidStack column.
 */
import { UidStack } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  /** CSS-зазор между элементами (token либо px), по умолчанию --uid-space-md. */
  gap?: string
}
withDefaults(defineProps<Props>(), { gap: 'var(--uid-space-md)' })
</script>

<template>
  <UidStack direction="column" :gap="gap" align="stretch">
    <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
  </UidStack>
</template>

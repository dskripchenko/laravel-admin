<script setup lang="ts">
/**
 * Rows: вертикальный список items сверху-вниз.
 */
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  gap?: number | string
}
withDefaults(defineProps<Props>(), { gap: 0 })
</script>

<template>
  <div class="admin-layout-rows" :style="{ gap: typeof gap === 'number' ? `${gap}px` : gap }">
    <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
  </div>
</template>

<style>
.admin-layout-rows {
  display: flex;
  flex-direction: column;
}
</style>

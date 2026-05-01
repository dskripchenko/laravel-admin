<script setup lang="ts">
/**
 * Section/Block layout — UidCard с заголовком + опциональным описанием.
 */
import { UidCard } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  title?: string | null
  description?: string | null
}

withDefaults(defineProps<Props>(), {
  title: null,
  description: null,
})
</script>

<template>
  <UidCard padding="md" class="admin-section">
    <header v-if="title || description" class="admin-section__header">
      <h3 v-if="title" class="admin-section__title">{{ title }}</h3>
      <p v-if="description" class="admin-section__description">{{ description }}</p>
    </header>
    <div class="admin-section__body">
      <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
    </div>
  </UidCard>
</template>

<style>
.admin-section { margin-bottom: var(--uid-space-md); }
.admin-section__header { margin-bottom: var(--uid-space-sm); }
.admin-section__title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-section__description {
  margin: var(--uid-space-2xs) 0 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-section__body {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
</style>

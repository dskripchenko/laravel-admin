<script setup lang="ts">
/**
 * Section: блок с заголовком + опциональным описанием.
 */
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  items: LayoutNode[]
  title?: string | null
  description?: string | null
  collapsible?: boolean
}
withDefaults(defineProps<Props>(), {
  title: null, description: null, collapsible: false,
})
</script>

<template>
  <section class="admin-layout-section">
    <header v-if="title || description" class="admin-layout-section__header">
      <h3 v-if="title" class="admin-layout-section__title">{{ title }}</h3>
      <p v-if="description" class="admin-layout-section__description">{{ description }}</p>
    </header>
    <div class="admin-layout-section__body">
      <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
    </div>
  </section>
</template>

<style>
.admin-layout-section {
  padding: 16px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  background: var(--admin-card-bg, #fff);
  margin-bottom: 16px;
}
.admin-layout-section__header { margin-bottom: 12px; }
.admin-layout-section__title { margin: 0; font-size: 14px; font-weight: 600; }
.admin-layout-section__description {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--admin-muted, #6b7280);
}
</style>

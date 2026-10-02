<script setup lang="ts">
/**
 * The Section/Block layout — a UidCard with a title, an optional description
 * and an optional icon before the title (Block::icon(), a name from the icon
 * registry).
 */
import { computed } from 'vue'
import { UidCard, UidIcon } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import { resolveIcon } from '../shell/iconRegistry'

interface Props {
  items: LayoutNode[]
  title?: string | null
  description?: string | null
  icon?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  description: null,
  icon: null,
})

const iconComponent = computed(() => resolveIcon(props.icon))
</script>

<template>
  <UidCard padding="md" class="admin-section">
    <header v-if="title || description" class="admin-section__header">
      <h3 v-if="title" class="admin-section__title">
        <UidIcon v-if="iconComponent" :icon="iconComponent" :size="16" class="admin-section__icon" />
        {{ title }}
      </h3>
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
  display: flex;
  align-items: center;
  gap: var(--uid-space-xs);
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-section__icon {
  flex: none;
  color: var(--uid-text-secondary);
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

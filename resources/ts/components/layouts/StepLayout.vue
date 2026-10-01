<script setup lang="ts">
/**
 * A Step outside a Wizard — the wizard draws its own steps, so a step met on
 * its own is shown for what it is: a titled group of its children.
 */
import SectionLayout from './SectionLayout.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

defineOptions({ inheritAttrs: false })

interface Props {
  items?: LayoutNode[]
  title?: string | null
  description?: string | null
}

withDefaults(defineProps<Props>(), {
  items: () => [],
  title: null,
  description: null,
})
</script>

<template>
  <SectionLayout :items="items" :title="title" :description="description" class="admin-step-layout" />
</template>

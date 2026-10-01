<script setup lang="ts">
/**
 * The Wrapper layout — a semantic element around its children, with no
 * styling of its own beyond the host's class.
 *
 * `tag` comes from the backend's `->tag()`; only the plain structural elements
 * are allowed, anything else falls back to a div. `className` comes from
 * `->className()`.
 */
import { computed } from 'vue'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

defineOptions({ inheritAttrs: false })

interface Props {
  items?: LayoutNode[]
  tag?: string | null
  className?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  tag: 'div',
  className: null,
})

const ALLOWED_TAGS: ReadonlySet<string> = new Set([
  'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'fieldset', 'span',
])

const element = computed<string>(() => {
  const tag = (props.tag ?? '').toLowerCase()
  return ALLOWED_TAGS.has(tag) ? tag : 'div'
})
</script>

<template>
  <component :is="element" :class="['admin-wrapper-layout', className]">
    <LayoutRenderer v-for="(child, idx) in items" :key="idx" :node="child" />
  </component>
</template>

<style>
/* Zero specificity: the default rhythm between the children, which any host
   class (a grid, a row) overrides whatever the stylesheet order. */
:where(.admin-wrapper-layout) {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
  min-width: 0;
}
</style>

<script setup lang="ts">
/**
 * The markdown widget: the content rendered by the built-in renderer, which
 * escapes the source first (raw HTML shows as text) and allows only safe
 * links. A host that needs the full CommonMark grammar registers its own
 * through registerWidget('markdown', ...).
 */
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'
import { renderMarkdown } from '../fields/support/markdown'

interface Props {
  title?: string
  content?: string | null
}

const props = withDefaults(defineProps<Props>(), { title: '', content: '' })

const html = computed<string>(() => renderMarkdown(props.content ?? ''))
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <!-- eslint-disable-next-line vue/no-v-html -- renderMarkdown escapes the source before adding markup -->
    <div class="admin-markdown admin-markdown-widget" v-html="html" />
  </UidCard>
</template>

<style>
.admin-markdown-widget {
  color: var(--uid-text-secondary);
}
</style>

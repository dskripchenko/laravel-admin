<script setup lang="ts">
/**
 * The markdown widget: the content drawn by the kit's UidMarkdown, which
 * escapes the source first (raw HTML shows as text) and allows only safe
 * links; ```mermaid blocks become diagrams. A host that needs the full
 * CommonMark grammar registers its own through registerWidget('markdown', ...).
 */
import { UidCard, UidMarkdown } from '@dskripchenko/ui'
import { useCalloutTitles } from '../markdown/calloutTitles'

interface Props {
  title?: string
  content?: string | null
}

withDefaults(defineProps<Props>(), { title: '', content: '' })

const calloutTitles = useCalloutTitles()
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <UidMarkdown
      class="admin-markdown admin-markdown-widget"
      :source="content ?? ''"
      :internal-links-in-place="false"
      :callout-titles="calloutTitles"
    />
  </UidCard>
</template>

<style>
.admin-markdown-widget {
  color: var(--uid-text-secondary);
}
</style>

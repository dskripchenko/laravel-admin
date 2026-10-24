<script setup lang="ts">
/**
 * MarkdownEntry — the view of a Markdown field: the source drawn by the kit's
 * UidMarkdown, which escapes it first (raw HTML shows as text); ```mermaid
 * blocks become diagrams.
 */
import { computed } from 'vue'
import { UidMarkdown } from '@dskripchenko/ui'
import { useEntryValue, isEmpty } from './entryValue'
import { useCalloutTitles } from '../markdown/calloutTitles'

interface Props {
  name?: string
  value?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  placeholder: '—',
})

const raw = useEntryValue(props)
const source = computed<string>(() => (isEmpty(raw.value) ? '' : String(raw.value)))
const calloutTitles = useCalloutTitles()
</script>

<template>
  <span v-if="source.trim() === ''" class="admin-infolist-text">{{ placeholder }}</span>
  <UidMarkdown
    v-else
    class="admin-markdown admin-infolist-markdown"
    :source="source"
    :internal-links-in-place="false"
    :callout-titles="calloutTitles"
  />
</template>

<style>
.admin-infolist-markdown {
  width: 100%;
}
</style>

<script setup lang="ts">
/**
 * MarkdownEntry — the view of a Markdown field: the source rendered by the
 * built-in renderer, which escapes it first (raw HTML shows as text).
 */
import { computed } from 'vue'
import { useEntryValue, isEmpty } from './entryValue'
import { renderMarkdown } from '../fields/support/markdown'

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
const html = computed<string>(() => (isEmpty(raw.value) ? '' : renderMarkdown(String(raw.value))))
</script>

<template>
  <span v-if="html === ''" class="admin-infolist-text">{{ placeholder }}</span>
  <!-- eslint-disable-next-line vue/no-v-html -- renderMarkdown escapes the source before adding markup -->
  <div v-else class="admin-markdown admin-infolist-markdown" v-html="html" />
</template>

<style>
.admin-infolist-markdown {
  width: 100%;
}
</style>

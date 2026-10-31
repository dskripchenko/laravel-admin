<script setup lang="ts">
/**
 * BpmnEntry — a stored BPMN model, read-only: the backend's
 * Infolist\BpmnEntry and the view of a Field\Bpmn on a view page. The BPMN
 * layout draws it (BpmnView: pan, zoom, the elements to outline). With
 * `notation` 'dmn' it is the DMN entry (DmnView; a double click on a decision
 * opens its table).
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { toText } from '../documents/useDocumentSource'
import BpmnLayout from '../layouts/BpmnLayout.vue'

// The field's attributes (properties, maxSize, …) reach an entry drawn for a
// field too; they are not the box's HTML attributes.
defineOptions({ inheritAttrs: false })

interface Props {
  name?: string
  value?: unknown
  notation?: 'bpmn' | 'dmn'
  height?: number | string | null
  highlight?: string[]
  diagram?: string | number | null
  decision?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  notation: 'bpmn',
  height: null,
  highlight: () => [],
  diagram: null,
  decision: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const xml = computed(() => toText(raw.value))
</script>

<template>
  <span v-if="xml.trim() === ''" class="admin-infolist-text">{{ placeholder }}</span>
  <BpmnLayout
    v-else
   
    :notation="notation"
    :content="xml"
    :height="height ?? '360px'"
    :highlight="highlight"
    :diagram="diagram"
    :decision="decision"
  />
</template>

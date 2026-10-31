<script setup lang="ts">
/**
 * The BPMN / DMN widget — the backend's BpmnWidget, or a user-added one: a
 * process or decision model shown read-only by the viewers of
 * @dskripchenko/bpmn, from inline XML or an address of the panel's site,
 * filling the widget.
 */
import BpmnLayout from '../layouts/BpmnLayout.vue'
import WidgetFrame from './WidgetFrame.vue'

defineOptions({ inheritAttrs: false })

withDefaults(defineProps<{
  title?: string | null
  notation?: 'bpmn' | 'dmn' | null
  content?: string | null
  url?: string | null
  highlight?: string[] | null
  decision?: string | null
  diagram?: string | number | null
}>(), {
  title: null,
  notation: 'bpmn',
  content: null,
  url: null,
  highlight: null,
  decision: null,
  diagram: null,
})
</script>

<template>
  <WidgetFrame :kind="notation === 'dmn' ? 'dmn' : 'bpmn'" :title="title">
    <BpmnLayout
      :notation="notation === 'dmn' ? 'dmn' : 'bpmn'"
      :content="url ? null : (content ?? '')"
      :url="url || null"
      :highlight="highlight ?? []"
      :decision="decision"
      :diagram="diagram"
      height="100%"
    />
  </WidgetFrame>
</template>

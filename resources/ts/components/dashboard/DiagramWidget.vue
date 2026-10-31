<script setup lang="ts">
/**
 * The diagram widget — the backend's DiagramWidget, or a user-added one: a
 * diagram in any notation of @dskripchenko/diagrams, drawn as
 * Layout::diagram() draws it, scrolling inside the widget.
 */
import DiagramLayout from '../layouts/DiagramLayout.vue'
import WidgetFrame from './WidgetFrame.vue'
import type { DiagramSyntax } from '../documents/loaders'

defineOptions({ inheritAttrs: false })

withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  syntax?: DiagramSyntax | null
}>(), { title: null, content: '', syntax: null })
</script>

<template>
  <WidgetFrame kind="diagram" :title="title">
    <DiagramLayout :content="content ?? ''" :syntax="syntax || null" height="100%" />
  </WidgetFrame>
</template>

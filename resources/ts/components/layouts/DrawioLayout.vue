<script setup lang="ts">
/**
 * The draw.io layout — `Layout::drawio(...)`: a draw.io (diagrams.net) file
 * drawn as SVG by @dskripchenko/drawio in a scrollable box. `page` picks a
 * page by its index, id or name; the first by default.
 *
 * The file is inline (`content`), a key of the screen's state (`source`) or
 * fetched from `url`. The package is loaded on first use; images from other
 * sites are not loaded (a viewer's request would reveal the diagram was
 * opened), embedded ones are shown.
 */
import { computed } from 'vue'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { loadDrawio } from '../documents/loaders'
import { cssHeight, messageOf, nextIdPrefix, useDocumentSource, useLazyModule } from '../documents/useDocumentSource'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  page?: string | number | null
  height?: string | number | null
}>(), {
  title: null,
  content: null,
  source: null,
  url: null,
  page: null,
  height: null,
})

const { text, loading, error, empty } = useDocumentSource(props)
const { module, failed } = useLazyModule(loadDrawio)
const idPrefix = nextIdPrefix('dw')

const drawing = computed<{ svg: string } | { error: string } | null>(() => {
  const m = module.value
  const source = text.value
  if (!m || source === null || source.trim() === '') return null
  try {
    return { svg: m.render(source, { page: props.page ?? undefined, idPrefix }).svg }
  } catch (err) {
    return { error: messageOf(err) }
  }
})

const problem = computed<string | null>(() => {
  if (failed.value) return tr('Не удалось загрузить модуль draw.io')
  if (error.value) return error.value
  const d = drawing.value
  return d && 'error' in d ? d.error : null
})
</script>

<template>
  <DocumentFrame
    kind="drawio"
    :title="title"
    :height="cssHeight(height)"
    :loading="loading || (!drawing && !empty)"
    :error="problem"
    :error-title="drawing && 'error' in drawing ? tr('Не удалось прочитать файл draw.io') : null"
    :empty="empty ? tr('Диаграмма пуста') : null"
    scroll
  >
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div v-if="drawing && 'svg' in drawing" class="admin-document-layout__svg" v-html="drawing.svg" />
  </DocumentFrame>
</template>

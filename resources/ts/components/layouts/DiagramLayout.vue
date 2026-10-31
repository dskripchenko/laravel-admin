<script setup lang="ts">
/**
 * The diagram layout — `Layout::diagram(...)`: a diagram drawn from text by
 * @dskripchenko/diagrams — Mermaid, PlantUML, DOT, D2, DBML, Structurizr or
 * a Wardley map. Without `syntax` the package tells the notation from the
 * source's start.
 *
 * The text is inline (`content`), a key of the screen's state (`source`) or
 * fetched from `url`. The package is loaded on first use; the SVG it returns
 * escapes every label by construction, which is what makes it safe to insert
 * as markup. A source it cannot read is shown as an alert with the line.
 */
import { computed } from 'vue'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { loadDiagrams, type DiagramSyntax } from '../documents/loaders'
import { cssHeight, messageOf, nextIdPrefix, useDocumentSource, useLazyModule } from '../documents/useDocumentSource'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  syntax?: DiagramSyntax | null
  height?: string | number | null
}>(), {
  title: null,
  content: null,
  source: null,
  url: null,
  syntax: null,
  height: null,
})

const { text, loading, error, empty } = useDocumentSource(props)
const { module, failed } = useLazyModule(loadDiagrams)
const idPrefix = nextIdPrefix('dg')

const drawing = computed<{ svg: string } | { error: string } | null>(() => {
  const m = module.value
  const source = text.value
  if (!m || source === null) return null
  if (source.trim() === '') return null
  try {
    return { svg: m.render(source, { syntax: props.syntax ?? undefined, idPrefix }).svg }
  } catch (err) {
    if (err instanceof m.DiagramError) {
      const where = err.line > 0 ? `${tr('Строка')} ${err.line}: ` : ''
      return { error: `${where}${err.message}${err.hint ? `. ${err.hint}` : ''}` }
    }
    return { error: messageOf(err) }
  }
})

const problem = computed<string | null>(() => {
  if (failed.value) return tr('Не удалось загрузить модуль диаграмм')
  if (error.value) return error.value
  const d = drawing.value
  return d && 'error' in d ? d.error : null
})
</script>

<template>
  <DocumentFrame
    kind="diagram"
    :title="title"
    :height="cssHeight(height)"
    :loading="loading || (!drawing && !empty)"
    :empty="empty ? tr('Диаграмма пуста') : null"
    :error="problem"
    :error-title="drawing && 'error' in drawing ? tr('Ошибка в диаграмме') : null"
    scroll
  >
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div v-if="drawing && 'svg' in drawing" class="admin-document-layout__svg" v-html="drawing.svg" />
  </DocumentFrame>
</template>

<script setup lang="ts">
/**
 * The BPMN and DMN layouts — `Layout::bpmn(...)` and `Layout::dmn(...)`: a
 * model shown read-only by BpmnView or DmnView of @dskripchenko/bpmn, with
 * pan and zoom. In DMN a double click on a decision opens its table.
 *
 * - `highlight` — the ids of the elements to outline;
 * - `diagram` — which diagram of a BPMN file that has several;
 * - `decision` — the DMN decision whose table is opened first.
 *
 * The XML is inline (`content`), a key of the screen's state (`source`) or
 * fetched from `url`. The package is loaded on first use; a file it cannot
 * read is shown as an alert. DmnLayout is this component with `notation`
 * set to 'dmn'.
 */
import { computed, ref, watch } from 'vue'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { loadBpmnVue } from '../documents/loaders'
import { messageOf, useDocumentSource, useLazyModule } from '../documents/useDocumentSource'
import { cssLength, flowLocale } from '../flow/loadFlow'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  notation?: 'bpmn' | 'dmn'
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  height?: string | number | null
  highlight?: string[]
  diagram?: string | number | null
  decision?: string | null
}>(), {
  notation: 'bpmn',
  title: null,
  content: null,
  source: null,
  url: null,
  height: null,
  highlight: () => [],
  diagram: null,
  decision: null,
})

const { text, loading, error, empty } = useDocumentSource(props)
const { module, failed } = useLazyModule(loadBpmnVue)

/** The viewer's report of a file it could not read; cleared by a new text. */
const parseError = ref<string | null>(null)
watch(text, () => {
  parseError.value = null
})

const viewer = computed(() => {
  const m = module.value
  if (!m) return null
  return props.notation === 'dmn' ? m.DmnView : m.BpmnView
})

const viewerProps = computed<Record<string, unknown>>(() => {
  const common = { xml: text.value, highlight: props.highlight, locale: flowLocale() }
  return props.notation === 'dmn'
    ? { ...common, decision: props.decision ?? undefined }
    : { ...common, diagram: props.diagram ?? undefined }
})

const problem = computed<string | null>(() => {
  if (failed.value) return tr('Не удалось загрузить модуль BPMN')
  return error.value ?? parseError.value
})

function onError(err: unknown): void {
  parseError.value = messageOf(err)
}
</script>

<template>
  <DocumentFrame
    :kind="notation"
    :title="title"
    :height="cssLength(height, '480px')"
    :loading="loading || !viewer || text === null"
    :error="problem"
    :error-title="parseError ? (notation === 'dmn' ? tr('Не удалось прочитать модель DMN') : tr('Не удалось прочитать модель BPMN')) : null"
    :empty="empty ? tr('Модель пуста') : null"
  >
    <component :is="viewer" v-bind="viewerProps" class="admin-document-layout__viewer" @error="onError" />
  </DocumentFrame>
</template>

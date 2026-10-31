<script setup lang="ts">
/**
 * BpmnField — the backend's Field\Bpmn (and, with `notation` 'dmn',
 * Field\Dmn): BpmnEditor or DmnEditor of @dskripchenko/bpmn bound to the
 * form state. The value is the model file's XML, a string; it is submitted
 * with the form like any other field and checked on the server.
 *
 * - `height` — the editor's height, 560px by default;
 * - `properties` (BPMN) — the properties panel open from the start;
 * - `diagram` (BPMN) — which diagram of a file that has several;
 * - `decision` (DMN) — the decision whose logic is opened first;
 * - `readonly` / `disabled` — the editor becomes a viewer.
 *
 * The package is loaded on first use. DmnField is this component with
 * `notation` set to 'dmn'.
 */
import { computed } from 'vue'
import { UidAlert, UidFormField, UidSkeleton } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { loadBpmnVue } from '../documents/loaders'
import { useLazyModule } from '../documents/useDocumentSource'
import { cssLength, flowLocale } from '../flow/loadFlow'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  name: string
  notation?: 'bpmn' | 'dmn'
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  height?: number | string | null
  properties?: boolean
  diagram?: string | number | null
  decision?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  notation: 'bpmn',
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  height: null,
  properties: false,
  diagram: null,
  decision: null,
})

const form = useFormState()
const { module, failed } = useLazyModule(loadBpmnVue)

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const xml = computed<string>(() => {
  const v = form.getField(props.name)
  return typeof v === 'string' ? v : ''
})

const editor = computed(() => {
  const m = module.value
  if (!m) return null
  return props.notation === 'dmn' ? m.DmnEditor : m.BpmnEditor
})

const editorProps = computed<Record<string, unknown>>(() => {
  const common = { modelValue: xml.value, readonly: props.readonly || props.disabled, locale: flowLocale() }
  return props.notation === 'dmn'
    ? { ...common, decision: props.decision ?? undefined }
    : { ...common, properties: props.properties, diagram: props.diagram ?? undefined }
})

function onUpdate(next: string): void {
  if (next !== xml.value) form.setField(props.name, next)
}

const boxStyle = computed(() => ({ height: cssLength(props.height, '560px') }))
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div
      class="admin-editor-field"
      :class="[`admin-editor-field--${notation}`, { 'admin-editor-field--invalid': !!errorMsg }]"
      :style="boxStyle"
    >
      <UidAlert v-if="failed" variant="danger">{{ tr('Не удалось загрузить модуль BPMN') }}</UidAlert>
      <UidSkeleton v-else-if="!editor" height="100%" />
      <component
        :is="editor"
        v-else
        v-bind="editorProps"
        class="admin-editor-field__editor"
        @update:model-value="onUpdate"
      />
    </div>
  </UidFormField>
</template>

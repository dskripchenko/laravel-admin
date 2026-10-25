<script setup lang="ts">
/**
 * FlowField — the backend's Field\Flow: FlowEditor of @dskripchenko/flow
 * bound to the form state. The value is the model, an object; it is
 * submitted with the form like any other field.
 *
 * - `palette` — the node types the elements panel offers; `locked` — types
 *   the editor will not let users change or delete.
 * - `readonly` / `disabled` — browse only.
 * - `mermaid` (on by default) — "Import Mermaid" in the JSON/Mermaid menu;
 *   the importer and @dskripchenko/diagrams load with the editor.
 * - A stored JSON string is read as well; what goes back is the object.
 *
 * The package is loaded on first use.
 */
import { computed, shallowRef, toRaw } from 'vue'
import { UidAlert, UidFormField, UidSkeleton } from '@dskripchenko/ui'
import type { Graph, NodeTypeDef, NodeTypeRegistry } from '@dskripchenko/flow'
import { useFormState } from '../render/formState'
import { blankGraph, cssLength, flowLocale, loadFlowMermaid, toGraph, useFlowModules } from '../flow/loadFlow'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  palette?: string[] | null
  locked?: string[] | null
  height?: number | string | null
  direction?: 'LR' | 'TB' | 'auto' | null
  edgeStyle?: 'orthogonal' | 'smooth' | null
  minimap?: boolean
  snapGrid?: number | null
  mermaid?: boolean
  exportName?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  palette: null,
  locked: null,
  height: null,
  direction: null,
  edgeStyle: null,
  minimap: false,
  snapGrid: null,
  mermaid: true,
  exportName: null,
})

const form = useFormState()
const { modules, failed } = useFlowModules()

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

// The model handed to the editor keeps its identity while the form holds the
// same value: FlowEditor resets its history on a model it did not produce.
let lastInput: unknown = Symbol('none')
let lastGraph: Graph = blankGraph()
const model = computed<Graph>(() => {
  const raw = toRaw(form.getField(props.name))
  if (raw !== lastInput) {
    lastInput = raw
    lastGraph = toGraph(raw) ?? blankGraph()
  }
  return lastGraph
})

function onUpdate(graph: Graph): void {
  form.setField(props.name, graph)
}

const registry = computed<NodeTypeRegistry | undefined>(() => {
  const core = modules.value?.core
  if (!core || !props.locked || props.locked.length === 0) return undefined
  const lockedTypes: Record<string, NodeTypeDef> = {}
  for (const type of props.locked) {
    const base = core.BUILTIN_TYPES[type as keyof typeof core.BUILTIN_TYPES] ?? core.BUILTIN_TYPES.state
    lockedTypes[type] = { ...base, locked: true }
  }
  return core.createRegistry(lockedTypes)
})

const fromMermaid = shallowRef<((source: string) => Graph) | undefined>()
if (props.mermaid) {
  loadFlowMermaid().then(
    (m) => {
      fromMermaid.value = m.fromMermaid
    },
    () => undefined,
  )
}

const boxStyle = computed(() => ({ height: cssLength(props.height, '520px') }))
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div class="admin-flow-field" :class="{ 'admin-flow-field--invalid': !!errorMsg }" :style="boxStyle">
      <UidAlert v-if="failed" variant="danger">{{ tr('Не удалось загрузить редактор процесса') }}</UidAlert>
      <UidSkeleton v-else-if="!modules" height="100%" />
      <component
        :is="modules.vue.FlowEditor"
        v-else
        class="admin-flow-field__editor"
        :model-value="model"
        :readonly="readonly || disabled"
        :palette="palette ?? undefined"
        :registry="registry"
        :direction="direction ?? undefined"
        :edge-style="edgeStyle ?? 'orthogonal'"
        :minimap="minimap"
        :snap-grid="snapGrid ?? 20"
        :mermaid="fromMermaid"
        :export-name="exportName ?? `${name}.json`"
        :locale="flowLocale()"
        :toast-provider="false"
        @update:model-value="onUpdate"
      />
    </div>
  </UidFormField>
</template>

<style>
.admin-flow-field {
  position: relative;
  width: 100%;
  border: 1px solid var(--uid-border-default, var(--uid-border-subtle));
  border-radius: var(--uid-radius-md);
  overflow: hidden;
  background: var(--uid-surface-base);
}
.admin-flow-field--invalid { border-color: var(--uid-danger); }
.admin-flow-field__editor { width: 100%; height: 100%; }
</style>

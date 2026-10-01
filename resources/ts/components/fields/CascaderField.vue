<script setup lang="ts">
/**
 * CascaderField — the backend's Field\Cascader over UidCascader: linked
 * levels, country → region → city. The state is the path, one value per
 * level.
 *
 * The options come from `->options($tree)` (the `tree` attribute) or, when
 * only `->levels([...])` is given, from the first level's `options`, whose
 * items carry the deeper levels in `children`. `->separator(' / ')` joins the
 * displayed path.
 */
import { computed } from 'vue'
import { UidCascader, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { normalizeTree, type OptionTreeItem, type OptionValue } from './support/tree'

interface Props {
  name: string
  tree?: unknown
  levels?: Array<{ key?: string; label?: string; options?: unknown }> | null
  separator?: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  tree: () => [],
  levels: null,
  separator: ' / ',
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  readonly: false,
})

const options = computed<OptionTreeItem[]>(() => {
  const fromTree = normalizeTree(props.tree)
  if (fromTree.length > 0) return fromTree
  return normalizeTree(props.levels?.[0]?.options)
})

/** A level's own label as the placeholder of an empty field: "Country". */
const placeholderText = computed<string | undefined>(() => {
  if (props.placeholder) return props.placeholder
  const labels = (props.levels ?? []).map((l) => l.label).filter((l): l is string => !!l)
  return labels.length > 0 ? labels.join(props.separator) : undefined
})

const form = useFormState()
/** The stored path, re-typed by the tree: '5' in the state finds the option 5. */
const value = computed<OptionValue[]>(() => {
  const v = form.getField(props.name)
  if (!Array.isArray(v)) return []
  const out: OptionValue[] = []
  let level: OptionTreeItem[] | undefined = options.value
  for (const step of v) {
    const node: OptionTreeItem | undefined = level?.find((n) => String(n.value) === String(step))
    out.push(node ? node.value : (step as OptionValue))
    level = node?.children
  }
  return out
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: OptionValue[]): void {
  if (props.readonly) return
  form.setField(props.name, next.length > 0 ? next : null)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <UidCascader
      :model-value="value"
      :options="options"
      :separator="separator"
      :placeholder="placeholderText"
      :disabled="disabled || readonly"
      :clearable="!required"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

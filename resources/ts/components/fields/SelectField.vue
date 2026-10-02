<script setup lang="ts">
/**
 * SelectField — the backend's Field\Select over UidSelect.
 *
 * With `->multiple()` the value is a list and the field is a UidTreeSelect
 * over flat nodes, the kit's multi-choice dropdown with removable tags:
 * UidSelect picks one value only.
 */
import { computed } from 'vue'
import { UidSelect, UidFormField, UidTreeSelect } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

export interface SelectOption {
  value: string | number
  label: string
  disabled?: boolean
}

interface Props {
  name: string
  options: SelectOption[]
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  /**
   * Read-only: the backend may mark a Select with `->readonly()`. UidSelect
   * does not tell readonly from disabled, so we map it to disabled, which
   * looks the same.
   */
  readonly?: boolean
  searchable?: boolean
  clearable?: boolean
  /** Select::multiple(): several values, stored as a list. */
  multiple?: boolean
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  readonly: false,
  searchable: false,
  clearable: false,
  multiple: false,
  size: 'md',
})

const isLocked = computed<boolean>(() => props.disabled || props.readonly)

const form = useFormState()
const value = computed<string | number | null>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined || v === '') return null
  return v as string | number
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string | number | null): void {
  form.setField(props.name, next)
}

const nodes = computed(() =>
  props.options.map((o) => ({ key: o.value, label: o.label, disabled: o.disabled })),
)

/**
 * The chosen values, as the options spell them: the state may hold 5 where
 * the options say '5', or a JSON string a cast left behind.
 */
const values = computed<Array<string | number>>(() => {
  let v: unknown = form.getField(props.name)
  if (typeof v === 'string' && v.trim().startsWith('[')) {
    try { v = JSON.parse(v) } catch { v = [] }
  }
  const list = Array.isArray(v) ? v : v === null || v === undefined || v === '' ? [] : [v]
  return list
    .filter((x): x is string | number => typeof x === 'string' || typeof x === 'number')
    .map((x) => props.options.find((o) => String(o.value) === String(x))?.value ?? x)
})

function onUpdateMany(next: unknown): void {
  if (props.readonly) return
  form.setField(props.name, Array.isArray(next) ? next : next === null || next === undefined ? [] : [next])
}
</script>

<template>
  <UidTreeSelect
    v-if="multiple"
    :model-value="values"
    :nodes="nodes"
    multiple
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :placeholder="placeholder ?? undefined"
    :disabled="isLocked"
    :clearable="clearable"
    @update:model-value="onUpdateMany"
  />
  <UidFormField
    v-else
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="isLocked"
  >
    <UidSelect
      :model-value="value"
      :options="options"
      :placeholder="placeholder ?? undefined"
      :disabled="isLocked"
      :searchable="searchable"
      :clearable="clearable"
      :size="size"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

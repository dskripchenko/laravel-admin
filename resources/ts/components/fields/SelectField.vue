<script setup lang="ts">
/**
 * SelectField — the backend's Field\Select over UidSelect.
 *
 * With `->multiple()` the value is a list: UidSelect in its multiple mode,
 * the chosen options shown as removable tags.
 */
import { computed } from 'vue'
import { UidSelect, UidFormField, type SelectValue } from '@dskripchenko/ui'
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

function onUpdate(next: SelectValue | null): void {
  form.setField(props.name, next)
}

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

function onUpdateMany(next: SelectValue[]): void {
  if (props.readonly) return
  form.setField(props.name, next)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="isLocked"
  >
    <UidSelect
      v-if="multiple"
      :model-value="values"
      :options="options"
      multiple
      :placeholder="placeholder ?? undefined"
      :disabled="isLocked"
      :searchable="searchable"
      :clearable="clearable"
      :size="size"
      @update:model-value="onUpdateMany"
    />
    <UidSelect
      v-else
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

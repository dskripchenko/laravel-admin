<script setup lang="ts">
/**
 * CheckboxField — the backend's Field\Checkbox.
 *
 * Without options it is one boolean checkbox, its caption inline. With
 * `->options([...])` it is a group of checkboxes, one per option, and the
 * value is the list of the checked options' values; `->inline()` lays the
 * group out in a row (UidCheckboxGroup).
 */
import { computed } from 'vue'
import { UidCheckbox, UidCheckboxGroup, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import type { SelectOption } from './SelectField.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  inlineLabel?: string | null
  disabled?: boolean
  readonly?: boolean
  options?: SelectOption[] | null
  inline?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inlineLabel: null,
  required: false,
  disabled: false,
  readonly: false,
  options: null,
  inline: false,
})

const form = useFormState()
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const isGroup = computed<boolean>(() => Array.isArray(props.options) && props.options.length > 0)
const locked = computed<boolean>(() => props.disabled || props.readonly)

const checked = computed<boolean>(() => Boolean(form.getField(props.name)))

function onUpdate(next: boolean): void {
  if (props.readonly) return
  form.setField(props.name, next)
}

/**
 * The checked values of a group, as the options spell them: the state may
 * hold '5' where the options say 5, or a JSON string a cast left behind.
 */
const selected = computed<Array<string | number>>(() => {
  let v: unknown = form.getField(props.name)
  if (typeof v === 'string' && v.trim().startsWith('[')) {
    try { v = JSON.parse(v) } catch { v = [] }
  }
  const list = Array.isArray(v) ? v : v === null || v === undefined || v === '' ? [] : [v]
  return list
    .filter((x): x is string | number => typeof x === 'string' || typeof x === 'number')
    .map((x) => (props.options ?? []).find((o) => String(o.value) === String(x))?.value ?? x)
})

function onGroupUpdate(next: Array<string | number>): void {
  if (props.readonly) return
  form.setField(props.name, next)
}
</script>

<template>
  <UidCheckboxGroup
    v-if="isGroup"
    :model-value="selected"
    :options="options ?? []"
    :name="`${name}[]`"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="locked"
    :direction="inline ? 'horizontal' : 'vertical'"
    @update:model-value="onGroupUpdate"
  />
  <!-- A single checkbox: its caption sits inline next to the box, not above
       it as a label, which would hang on a line of its own over an empty box.
       The hint and the error stay under the row. -->
  <UidFormField
    v-else
    :hint="help ?? undefined"
    :error="errorMsg"
    :disabled="locked"
  >
    <UidCheckbox
      :model-value="checked"
      :disabled="locked"
      :required="required"
      :name="name"
      :label="inlineLabel ?? label ?? undefined"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>


<script setup lang="ts">
/**
 * RadioField — the backend's Field\Radio: a UidRadioGroup over the options
 * from `->options([...])` or `->fromEnum(...)`. `->inline()` lays the
 * choices out in a row.
 */
import { computed } from 'vue'
import { UidRadio, UidRadioGroup } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import type { SelectOption } from './SelectField.vue'

interface Props {
  name: string
  options?: SelectOption[]
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  inline?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  options: () => [],
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  inline: false,
})

const form = useFormState()
/**
 * The state may hold 5 while the options say '5' (or the other way round):
 * the radio's value is matched by its string form, so the stored type wins.
 */
const value = computed<string | number | undefined>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined || v === '') return undefined
  const match = props.options.find((o) => String(o.value) === String(v))
  return match ? match.value : (v as string | number)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string | number | boolean | null | undefined): void {
  if (props.readonly) return
  form.setField(props.name, next ?? null)
}
</script>

<template>
  <UidRadioGroup
    :model-value="value"
    :name="name"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled || readonly"
    :direction="inline ? 'horizontal' : 'vertical'"
    @update:model-value="onUpdate"
  >
    <UidRadio
      v-for="o in options"
      :key="String(o.value)"
      :value="o.value"
      :label="o.label"
      :disabled="o.disabled"
    />
  </UidRadioGroup>
</template>

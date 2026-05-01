<script setup lang="ts">
import { computed } from 'vue'
import { UidNumberInput, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  min?: number | null
  max?: number | null
  step?: number | null
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  min: null,
  max: null,
  step: null,
  required: false,
  disabled: false,
  readonly: false,
})

const form = useFormState()
const value = computed<number | null>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined || v === '') return null
  return typeof v === 'number' ? v : Number(v)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: number | null): void {
  form.setField(props.name, next)
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
    <UidNumberInput
      :model-value="value"
      :min="min ?? undefined"
      :max="max ?? undefined"
      :step="step ?? undefined"
      :placeholder="placeholder ?? undefined"
      :disabled="disabled"
      :readonly="readonly"
      :name="name"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

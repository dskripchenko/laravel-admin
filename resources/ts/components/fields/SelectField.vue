<script setup lang="ts">
import { computed } from 'vue'
import { UidSelect, UidFormField } from '@dskripchenko/ui'
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
  searchable?: boolean
  clearable?: boolean
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  searchable: false,
  clearable: false,
  size: 'md',
})

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
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <UidSelect
      :model-value="value"
      :options="options"
      :placeholder="placeholder ?? undefined"
      :disabled="disabled"
      :searchable="searchable"
      :clearable="clearable"
      :size="size"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

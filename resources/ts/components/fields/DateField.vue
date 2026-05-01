<script setup lang="ts">
import { computed } from 'vue'
import { UidDatePicker, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /**
   * Тип input'а: 'date' (default), 'datetime-local', 'time'.
   * UidDatePicker внутри обрабатывает формат через свои props; для совместимости
   * со старым API оставляем prop.
   */
  inputType?: 'date' | 'datetime-local' | 'time'
  min?: string | null
  max?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inputType: 'date',
  min: null,
  max: null,
  required: false,
  disabled: false,
})

const form = useFormState()
const value = computed<string | null>(() => {
  const v = form.getField(props.name)
  return v === null || v === undefined || v === '' ? null : String(v)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string | null): void {
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
    <UidDatePicker
      :model-value="value"
      :min="min ?? undefined"
      :max="max ?? undefined"
      :disabled="disabled"
      :name="name"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

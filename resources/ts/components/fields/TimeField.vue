<script setup lang="ts">
/**
 * TimeField — the backend's Field\TimePicker over UidTimePicker. The value is
 * 'HH:mm', or 'HH:mm:ss' with `->withSeconds()` or a format carrying seconds
 * ('H:i:s'); `->step(15)` is the minutes' step.
 */
import { computed } from 'vue'
import { UidFormField, UidTimePicker } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  /** The PHP date format: 'H:i' by default, 'H:i:s' with seconds. */
  format?: string | null
  withSeconds?: boolean
  /** The minutes' step. */
  step?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  readonly: false,
  format: null,
  withSeconds: false,
  step: null,
})

const seconds = computed<boolean>(() => props.withSeconds || (props.format ?? '').includes(':s'))

const form = useFormState()
const value = computed<string | null>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined || v === '') return null
  // A datetime ('2026-01-02 10:30:00' or ISO) keeps only its time part.
  const m = /(\d{1,2}:\d{2}(?::\d{2})?)/.exec(String(v))
  return m ? (m[1] ?? null) : null
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string | null): void {
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
    :disabled="disabled"
  >
    <UidTimePicker
      :model-value="value"
      :with-seconds="seconds"
      :step="step ?? undefined"
      :placeholder="placeholder ?? undefined"
      :disabled="disabled || readonly"
      :clearable="!required"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

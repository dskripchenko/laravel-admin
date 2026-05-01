<script setup lang="ts">
import { computed } from 'vue'
import { UidTextarea, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  rows?: number
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  rows: 4,
  required: false,
  disabled: false,
  readonly: false,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string): void {
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
    <UidTextarea
      :model-value="value"
      :rows="rows"
      :placeholder="placeholder ?? undefined"
      :disabled="disabled"
      :readonly="readonly"
      :name="name"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { UidTextarea, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { counterHint } from './support/counter'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  rows?: number
  disabled?: boolean
  readonly?: boolean
  /** A character counter in the hint (Field::counter()). */
  counter?: boolean
  /** The most characters it takes (Field::counter($max), ->maxlength()). */
  maxlength?: number | null
  /** A recommended length that caps nothing (Field::counter($max, soft: true)). */
  counterLimit?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  rows: 4,
  required: false,
  disabled: false,
  readonly: false,
  counter: false,
  maxlength: null,
  counterLimit: null,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const hint = computed<string | undefined>(() => counterHint(props.help, value.value, props.counter, props.maxlength, props.counterLimit))

function onUpdate(next: string): void {
  form.setField(props.name, next)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="hint"
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
    :maxlength="maxlength ?? undefined"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

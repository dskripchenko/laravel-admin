<script setup lang="ts">
/**
 * TextField — a wrapper around UidInput from @dskripchenko/ui, bound both ways
 * to the form state through provideFormState and useFormState. The input's
 * type — text, email, url, password, tel, search — is passed on as `type`.
 */
import { computed } from 'vue'
import { UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { counterHint } from './support/counter'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  inputType?: 'text' | 'email' | 'url' | 'password' | 'tel' | 'search'
  disabled?: boolean
  readonly?: boolean
  /** A character counter in the hint (Field::counter()). */
  counter?: boolean
  /** The most characters it takes (Field::counter($max), ->maxlength()). */
  maxlength?: number | null
  /** A recommended length that caps nothing (Field::counter($max, soft: true)). */
  counterLimit?: number | null
  autocomplete?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  inputType: 'text',
  required: false,
  disabled: false,
  readonly: false,
  counter: false,
  maxlength: null,
  counterLimit: null,
  autocomplete: null,
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
  <UidInput
    :model-value="value"
    :type="inputType"
    :label="label ?? undefined"
    :hint="hint"
    :error="errorMsg"
    :placeholder="placeholder ?? undefined"
    :required="required"
    :disabled="disabled"
    :readonly="readonly"
    :name="name"
    :maxlength="maxlength ?? undefined"
    :autocomplete="autocomplete ?? undefined"
    @update:model-value="onUpdate"
  />
</template>

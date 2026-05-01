<script setup lang="ts">
/**
 * TextField — wrapper над UidInput из @dskripchenko/ui. Двусторонняя связь
 * с form-state через provideFormState/useFormState. Тип input'а (text/email/
 * url/password/tel/search) пробрасывается как `type`.
 */
import { computed } from 'vue'
import { UidInput } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  inputType?: 'text' | 'email' | 'url' | 'password' | 'tel' | 'search'
  disabled?: boolean
  readonly?: boolean
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
  autocomplete: null,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string): void {
  form.setField(props.name, next)
}
</script>

<template>
  <UidInput
    :model-value="value"
    :type="inputType"
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :placeholder="placeholder ?? undefined"
    :required="required"
    :disabled="disabled"
    :readonly="readonly"
    :name="name"
    :autocomplete="autocomplete ?? undefined"
    @update:model-value="onUpdate"
  />
</template>

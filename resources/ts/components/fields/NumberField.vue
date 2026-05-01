<script setup lang="ts">
import { computed } from 'vue'
import { useFormState } from '../render/formState'
import FieldShell from './FieldShell.vue'

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
  label: null, help: null, placeholder: null,
  min: null, max: null, step: null,
  required: false, disabled: false, readonly: false,
})

const form = useFormState()
const value = computed<number | string>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined) return ''
  return v as number
})

function onInput(event: Event): void {
  const t = event.target as HTMLInputElement
  if (t.value === '') {
    form.setField(props.name, null)
    return
  }
  const num = Number(t.value)
  form.setField(props.name, Number.isNaN(num) ? null : num)
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <input
        :id="id"
        type="number"
        :value="value"
        :placeholder="placeholder ?? undefined"
        :min="min ?? undefined"
        :max="max ?? undefined"
        :step="step ?? undefined"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :name="name"
        class="admin-input"
        @input="onInput"
      />
    </template>
  </FieldShell>
</template>

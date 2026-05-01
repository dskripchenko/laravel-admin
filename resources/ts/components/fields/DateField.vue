<script setup lang="ts">
import { computed } from 'vue'
import { useFormState } from '../render/formState'
import FieldShell from './FieldShell.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /** date | datetime-local | time. */
  inputType?: 'date' | 'datetime-local' | 'time'
  min?: string | null
  max?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null, help: null,
  inputType: 'date', min: null, max: null,
  required: false, disabled: false,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')

function onInput(event: Event): void {
  const t = event.target as HTMLInputElement
  form.setField(props.name, t.value === '' ? null : t.value)
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <input
        :id="id"
        :type="inputType"
        :value="value"
        :min="min ?? undefined"
        :max="max ?? undefined"
        :disabled="disabled"
        :required="required"
        :name="name"
        class="admin-input"
        @input="onInput"
      />
    </template>
  </FieldShell>
</template>

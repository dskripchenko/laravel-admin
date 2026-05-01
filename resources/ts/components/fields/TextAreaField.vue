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
  rows?: number
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null, help: null, placeholder: null, rows: 4,
  required: false, disabled: false, readonly: false,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')

function onInput(event: Event): void {
  const t = event.target as HTMLTextAreaElement
  form.setField(props.name, t.value)
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <textarea
        :id="id"
        :rows="rows"
        :value="value"
        :placeholder="placeholder ?? undefined"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :name="name"
        class="admin-textarea"
        @input="onInput"
      />
    </template>
  </FieldShell>
</template>

<style>
.admin-textarea {
  width: 100%;
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid var(--admin-border, #d1d5db);
  border-radius: 6px;
  background: var(--admin-input-bg, #fff);
  color: var(--admin-text, #111827);
  resize: vertical;
  font-family: inherit;
}
.admin-textarea:focus {
  outline: 2px solid var(--admin-accent, #3b82f6);
  outline-offset: -1px;
}
</style>

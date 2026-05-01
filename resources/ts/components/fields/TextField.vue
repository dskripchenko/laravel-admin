<script setup lang="ts">
/**
 * Универсальный текстовый input. type выбирается props'ом ('text'|'email'|
 * 'url'|'password'|'tel') — для number-input'а есть отдельный NumberField.
 */
import { computed } from 'vue'
import { useFormState } from '../render/formState'
import FieldShell from './FieldShell.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  inputType?: 'text' | 'email' | 'url' | 'password' | 'tel' | 'search'
  disabled?: boolean
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null, help: null, placeholder: null,
  required: false, disabled: false, readonly: false,
  inputType: 'text',
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | null | undefined) ?? '')

function onInput(event: Event): void {
  const t = event.target as HTMLInputElement
  form.setField(props.name, t.value)
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <input
        :id="id"
        :type="inputType"
        :value="value"
        :placeholder="placeholder ?? undefined"
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

<style>
.admin-input {
  width: 100%;
  padding: 6px 10px;
  height: 32px;
  font-size: 13px;
  border: 1px solid var(--admin-border, #d1d5db);
  border-radius: 6px;
  background: var(--admin-input-bg, #fff);
  color: var(--admin-text, #111827);
}
.admin-input:focus {
  outline: 2px solid var(--admin-accent, #3b82f6);
  outline-offset: -1px;
}
</style>

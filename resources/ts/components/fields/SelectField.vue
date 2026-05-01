<script setup lang="ts">
import { computed } from 'vue'
import { useFormState } from '../render/formState'
import FieldShell from './FieldShell.vue'

export interface SelectOption {
  value: string | number
  label: string
  disabled?: boolean
}

interface Props {
  name: string
  options: SelectOption[]
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  multiple?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null, help: null, placeholder: null,
  required: false, multiple: false, disabled: false,
})

const form = useFormState()
const value = computed(() => {
  const v = form.getField(props.name)
  if (props.multiple) return Array.isArray(v) ? v : []
  return v ?? ''
})

function onChange(event: Event): void {
  const t = event.target as HTMLSelectElement
  if (props.multiple) {
    const values: (string | number)[] = []
    for (const option of t.selectedOptions) {
      values.push(option.value)
    }
    form.setField(props.name, values)
  } else {
    form.setField(props.name, t.value === '' ? null : t.value)
  }
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <select
        :id="id"
        :value="value"
        :multiple="multiple"
        :disabled="disabled"
        :required="required"
        :name="name"
        class="admin-select"
        @change="onChange"
      >
        <option v-if="!multiple && placeholder" value="" disabled>
          {{ placeholder }}
        </option>
        <option
          v-for="opt in options"
          :key="String(opt.value)"
          :value="opt.value"
          :disabled="opt.disabled"
        >
          {{ opt.label }}
        </option>
      </select>
    </template>
  </FieldShell>
</template>

<style>
.admin-select {
  width: 100%;
  padding: 6px 10px;
  height: 32px;
  font-size: 13px;
  border: 1px solid var(--admin-border, #d1d5db);
  border-radius: 6px;
  background: var(--admin-input-bg, #fff);
  color: var(--admin-text, #111827);
}
.admin-select[multiple] { height: auto; min-height: 80px; }
.admin-select:focus {
  outline: 2px solid var(--admin-accent, #3b82f6);
  outline-offset: -1px;
}
</style>

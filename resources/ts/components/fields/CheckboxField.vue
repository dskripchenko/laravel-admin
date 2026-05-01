<script setup lang="ts">
import { computed } from 'vue'
import { useFormState } from '../render/formState'
import FieldShell from './FieldShell.vue'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /** Inline-текст рядом с чекбоксом (часто отличается от верхнего label). */
  inlineLabel?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null, help: null, inlineLabel: null,
  required: false, disabled: false,
})

const form = useFormState()
const checked = computed<boolean>(() => Boolean(form.getField(props.name)))

function onChange(event: Event): void {
  const t = event.target as HTMLInputElement
  form.setField(props.name, t.checked)
}
</script>

<template>
  <FieldShell :name="name" :label="label" :help="help" :required="required">
    <template #default="{ id }">
      <label class="admin-checkbox">
        <input
          :id="id"
          type="checkbox"
          :checked="checked"
          :disabled="disabled"
          :name="name"
          @change="onChange"
        />
        <span v-if="inlineLabel" class="admin-checkbox__label">{{ inlineLabel }}</span>
      </label>
    </template>
  </FieldShell>
</template>

<style>
.admin-checkbox {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
}
.admin-checkbox input { width: 16px; height: 16px; cursor: pointer; }
</style>

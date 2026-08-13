<script setup lang="ts">
/**
 * A field with suggestions that still accepts anything typed in.
 *
 * The case it exists for: a value that comes from a known list which we do not
 * own. A provider's model identifier is exactly that — `claude-opus-5` has to
 * be spelt precisely, remembering it is unreasonable, and a closed select would
 * go stale the day the provider ships a new model.
 *
 * So the list is a hint, not a rule: `allowCreate` keeps whatever was typed.
 */
import { computed } from 'vue'
import { UidCombobox, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

export interface ComboboxFieldOption {
  value: string | number
  label: string
  hint?: string
  disabled?: boolean
}

interface Props {
  name: string
  options: ComboboxFieldOption[]
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  /**
   * Whether a value outside the list is allowed. The backend calls it
   * `creatable` (Field\Combobox::creatable), and that name is kept here so the
   * attribute travels straight through. On by default — that is the point.
   */
  creatable?: boolean
  clearable?: boolean
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  readonly: false,
  creatable: true,
  clearable: true,
  size: 'md',
})

const isLocked = computed<boolean>(() => props.disabled || props.readonly)

const form = useFormState()
const value = computed<string | number | null>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined || v === '') return null
  return v as string | number
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

/**
 * A saved value outside the list still has to be shown. Without this the field
 * would open empty on a record whose value nobody suggested — and look like
 * data loss.
 */
const options = computed<ComboboxFieldOption[]>(() => {
  const current = value.value
  if (current === null || props.options.some((o) => o.value === current)) return props.options

  return [{ value: current, label: String(current) }, ...props.options]
})

function onUpdate(next: string | number | null): void {
  form.setField(props.name, next)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="isLocked"
  >
    <UidCombobox
      :model-value="value"
      :options="options"
      :placeholder="placeholder ?? undefined"
      :disabled="isLocked"
      :allow-create="creatable"
      :clearable="clearable"
      :size="size"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

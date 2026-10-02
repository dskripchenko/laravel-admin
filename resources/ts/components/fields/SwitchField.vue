<script setup lang="ts">
/**
 * SwitchField — the backend's Field\Switcher over UidSwitch.
 *
 * Switcher::labels($on, $off) puts the state's caption next to the switch;
 * the field's own label then sits above it. Without them the field's label is
 * the caption, inline, as with a checkbox.
 */
import { computed } from 'vue'
import { UidFormField, UidSwitch } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  inlineLabel?: string | null
  disabled?: boolean
  readonly?: boolean
  onLabel?: string | null
  offLabel?: string | null
  size?: 'sm' | 'md' | 'lg' | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inlineLabel: null,
  required: false,
  disabled: false,
  readonly: false,
  onLabel: null,
  offLabel: null,
  size: null,
})

const form = useFormState()
const checked = computed<boolean>(() => Boolean(form.getField(props.name)))
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const hasStateLabels = computed<boolean>(() => Boolean(props.onLabel || props.offLabel))
const caption = computed<string | undefined>(() => {
  if (hasStateLabels.value) return (checked.value ? props.onLabel : props.offLabel) ?? undefined
  return props.inlineLabel ?? props.label ?? undefined
})

function onUpdate(next: boolean): void {
  if (props.readonly) return
  form.setField(props.name, next)
}
</script>

<template>
  <UidFormField
    :label="hasStateLabels ? (label ?? undefined) : undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="hasStateLabels && required"
    :disabled="disabled"
  >
    <UidSwitch
      :model-value="checked"
      :disabled="disabled || readonly"
      :required="required"
      :name="name"
      :size="size ?? undefined"
      :label="caption"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

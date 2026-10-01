<script setup lang="ts">
/**
 * ColorField — the backend's Field\ColorPicker over UidColorPicker.
 *
 * UidColorPicker speaks hex only, so the stored value is converted on the way
 * in and out: `->format('rgb')` or `->format('hsl')` stores rgb()/hsl()
 * strings, `->withAlpha()` adds the alpha channel (rgba/hsla, or 8-digit
 * hex), `->palette([...])` becomes the picker's presets.
 */
import { computed } from 'vue'
import { UidColorPicker, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { formatColor, parseColor, toHex, type ColorFormat } from './support/color'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  format?: ColorFormat
  palette?: string[] | null
  withAlpha?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  format: 'hex',
  palette: null,
  withAlpha: false,
})

const form = useFormState()
const value = computed<string | null>(() => toHex(form.getField(props.name)))
const presets = computed<string[] | undefined>(() => {
  if (!props.palette || props.palette.length === 0) return undefined
  return props.palette.map((c) => toHex(c)).filter((c): c is string => c !== null)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string | null): void {
  if (props.readonly) return
  const parsed = parseColor(next)
  if (parsed === null) {
    form.setField(props.name, null)
    return
  }
  if (!props.withAlpha) parsed.a = 1
  form.setField(props.name, formatColor(parsed, props.format))
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <UidColorPicker
      :model-value="value"
      :presets="presets"
      :alpha="withAlpha"
      :disabled="disabled || readonly"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

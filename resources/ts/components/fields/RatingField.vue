<script setup lang="ts">
/**
 * RatingField — the backend's Field\Rating over UidRating: `->count(n)` stars
 * (5 by default), `->half()` for half steps, `->icon('heart')` for a lucide
 * icon in place of the star.
 */
import { computed } from 'vue'
import * as LucideIcons from 'lucide-vue-next'
import { UidFormField, UidRating } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  count?: number
  half?: boolean
  /** A lucide icon name in kebab-case: 'heart', 'thumbs-up'. */
  icon?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  count: 5,
  half: false,
  icon: null,
})

const form = useFormState()
const value = computed<number>(() => {
  const v = Number(form.getField(props.name))
  return Number.isFinite(v) ? v : 0
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const iconComponent = computed<unknown>(() => {
  if (!props.icon) return undefined
  const key = props.icon
    .split('-')
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join('')
  return (LucideIcons as Record<string, unknown>)[key]
})

function onUpdate(next: number): void {
  form.setField(props.name, next)
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
    <UidRating
      :model-value="value"
      :max="count"
      :allow-half="half"
      :readonly="readonly"
      :disabled="disabled"
      :icon="iconComponent"
      :label="label ?? name"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

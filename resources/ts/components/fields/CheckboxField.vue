<script setup lang="ts">
import { computed } from 'vue'
import { UidCheckbox, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  inlineLabel?: string | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inlineLabel: null,
  required: false,
  disabled: false,
})

const form = useFormState()
const checked = computed<boolean>(() => Boolean(form.getField(props.name)))
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: boolean): void {
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
    <UidCheckbox
      :model-value="checked"
      :disabled="disabled"
      :name="name"
      @update:model-value="onUpdate"
    >
      <template v-if="inlineLabel">{{ inlineLabel }}</template>
    </UidCheckbox>
  </UidFormField>
</template>

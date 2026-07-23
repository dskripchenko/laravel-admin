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
  <!-- Чекбокс/переключатель: подпись ИНЛАЙН рядом с контролом (не отдельным
       label'ом сверху) — иначе лейбл висит на новой строке над пустым
       чекбоксом. hint/error по-прежнему под строкой. -->
  <UidFormField
    :hint="help ?? undefined"
    :error="errorMsg"
    :disabled="disabled"
  >
    <UidCheckbox
      :model-value="checked"
      :disabled="disabled"
      :required="required"
      :name="name"
      :label="inlineLabel ?? label ?? undefined"
      @update:model-value="onUpdate"
    />
  </UidFormField>
</template>

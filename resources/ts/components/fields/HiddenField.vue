<script setup lang="ts">
/**
 * HiddenField — the backend's Field\Hidden: nothing visible, but the value
 * stays in the form state and goes out with the submit. An explicit
 * `->value(...)` seeds the state when it holds nothing yet.
 */
import { computed } from 'vue'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  value?: unknown
}

const props = withDefaults(defineProps<Props>(), {
  value: undefined,
})

const form = useFormState()
const current = form.getField(props.name)
if ((current === undefined || current === null) && props.value !== undefined && props.value !== null) {
  form.setField(props.name, props.value)
}

const serialized = computed<string>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined) return ''
  return typeof v === 'object' ? JSON.stringify(v) : String(v)
})
</script>

<template>
  <input type="hidden" :name="name" :value="serialized">
</template>

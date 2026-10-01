<script setup lang="ts">
/**
 * LabelField — the backend's Field\Label: static, read-only text inside a
 * form. Not an input: the text comes from `->value(...)` or from the state by
 * name, and nothing is written back.
 */
import { computed } from 'vue'
import { UidFormField } from '@dskripchenko/ui'
import { tryUseFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  value?: unknown
  placeholder?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  value: undefined,
  placeholder: null,
})

const form = tryUseFormState()
const text = computed<string>(() => {
  const v = props.value !== undefined && props.value !== null ? props.value : form?.getField(props.name)
  if (v === null || v === undefined || v === '') return props.placeholder ?? '—'
  return typeof v === 'object' ? JSON.stringify(v) : String(v)
})
</script>

<template>
  <UidFormField :label="label ?? undefined" :hint="help ?? undefined">
    <div class="admin-label-field" :data-name="name">{{ text }}</div>
  </UidFormField>
</template>

<style>
.admin-label-field {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
  white-space: pre-wrap;
  word-break: break-word;
  padding: var(--uid-space-2xs, 2px) 0;
}
</style>

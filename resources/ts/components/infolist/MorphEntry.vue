<script setup lang="ts">
/**
 * MorphEntry — the view of a MorphSwitcher field: the type and the record,
 * the record's label taken from the type's options when they are there
 * ("post: Hello world"), its id otherwise ("post #42").
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { optionLabel } from '../fields/support/tree'

interface Props {
  name?: string
  value?: unknown
  morphTypes?: Record<string, { label?: string; options?: unknown }> | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  morphTypes: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const text = computed<string>(() => {
  const v = raw.value
  if (!v || typeof v !== 'object') return props.placeholder
  const r = v as Record<string, unknown>
  if (typeof r.type !== 'string' || r.type === '') return props.placeholder
  const def = props.morphTypes?.[r.type]
  const typeLabel = def?.label ?? r.type
  if (r.id === null || r.id === undefined || r.id === '') return typeLabel
  const label = optionLabel(def?.options ?? [], r.id)
  return label === String(r.id) ? `${typeLabel} #${label}` : `${typeLabel}: ${label}`
})
</script>

<template>
  <span class="admin-infolist-text">{{ text }}</span>
</template>

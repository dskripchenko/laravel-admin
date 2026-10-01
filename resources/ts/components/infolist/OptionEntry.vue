<script setup lang="ts">
/**
 * OptionEntry — the view of a field with options (Radio): the chosen option's
 * label rather than its stored key. A list of values shows every label.
 */
import { computed } from 'vue'
import { useEntryValue, isEmpty } from './entryValue'
import { optionLabel } from '../fields/support/tree'

interface Props {
  name?: string
  value?: unknown
  options?: unknown
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  options: () => [],
  placeholder: '—',
})

const raw = useEntryValue(props)
const text = computed<string>(() => {
  const v = raw.value
  if (isEmpty(v)) return props.placeholder
  const list = Array.isArray(v) ? v : [v]
  return list.map((x) => optionLabel(props.options, x)).join(', ')
})
</script>

<template>
  <span class="admin-infolist-text">{{ text }}</span>
</template>

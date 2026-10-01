<script setup lang="ts">
/**
 * DateRangeEntry — the view of a DateRange field: "01.03.2026 — 31.03.2026",
 * with an open end shown as an ellipsis.
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { readDateRange } from '../fields/support/dateRange'
import { formatCell } from '../resource/cellFormat'

interface Props {
  name?: string
  value?: unknown
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  placeholder: '—',
})

const raw = useEntryValue(props)
const text = computed<string>(() => {
  const { start, end } = readDateRange(raw.value)
  if (start === null && end === null) return props.placeholder
  const fmt = (d: string | null): string => (d === null ? '…' : formatCell(d, 'date'))
  return `${fmt(start)} — ${fmt(end)}`
})
</script>

<template>
  <span class="admin-infolist-text">{{ text }}</span>
</template>

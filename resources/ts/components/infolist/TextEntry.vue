<script setup lang="ts">
/**
 * TextEntry — read-only string value. The label-above-value row wrapper
 * is provided by InfolistRenderer (`admin-infolist-entry`) so every
 * entry type aligns the same way; this component only renders the
 * formatted value itself.
 *
 * Auto-formatting via formatCell (see resource/cellFormat.ts): ISO
 * datetimes become d.m.Y H:i:s, money/bytes/boolean presets, etc.
 */
import { computed } from 'vue'
import { tryUseRecord } from './recordContext'
import { formatCell } from '../resource/cellFormat'

interface Props {
  name?: string
  label?: string
  value?: string | number | null
  /** Заместитель пустого значения. */
  placeholder?: string
  /** Применить mono-font (например для ID/UUID). */
  mono?: boolean
  /** Preset форматтер: datetime/date/money/boolean/bytes. */
  preset?: string
  meta?: Record<string, unknown>
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  placeholder: '—',
  mono: false,
  preset: undefined,
  meta: () => ({}),
})

const record = tryUseRecord()
const display = computed<string>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = record[props.name]
  }
  if (v === null || v === undefined || v === '') return props.placeholder
  const formatted = formatCell(v, props.preset, props.meta as Record<string, unknown>)
  return formatted === '' ? props.placeholder : formatted
})
</script>

<template>
  <span
    :class="['admin-infolist-text', mono ? 'admin-infolist-text--mono' : '']"
  >
    {{ display }}
  </span>
</template>

<style>
.admin-infolist-text {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
  word-break: break-word;
  white-space: pre-wrap;
}
.admin-infolist-text--mono {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}
</style>

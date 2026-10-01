<script setup lang="ts">
/**
 * ColorEntry — a colour swatch with its value alongside (the backend's
 * Infolist\ColorEntry, and the view of a ColorPicker field).
 * `->showValue(false)` leaves the swatch alone; `->format('rgb' | 'hsl')`
 * converts the displayed value.
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { formatColor, parseColor, type ColorFormat } from '../fields/support/color'

interface Props {
  name?: string
  value?: string | null
  showValue?: boolean
  placeholder?: string
  meta?: Record<string, unknown>
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  showValue: true,
  placeholder: '—',
  meta: () => ({}),
})

const raw = useEntryValue(props)
const parsed = computed(() => parseColor(raw.value))
const css = computed<string | null>(() => (parsed.value ? formatColor(parsed.value, 'rgb') : null))
const text = computed<string>(() => {
  if (!parsed.value) return typeof raw.value === 'string' && raw.value !== '' ? raw.value : props.placeholder
  const fmt = props.meta.format
  return fmt === 'hex' || fmt === 'rgb' || fmt === 'hsl'
    ? formatColor(parsed.value, fmt as ColorFormat)
    : String(raw.value)
})
</script>

<template>
  <span class="admin-infolist-color">
    <span
      v-if="css"
      class="admin-infolist-color__swatch"
      :style="{ background: css }"
      role="img"
      :aria-label="text"
    />
    <span v-if="showValue || !css" class="admin-infolist-color__value">{{ text }}</span>
  </span>
</template>

<style>
.admin-infolist-color {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs, 6px);
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
}
.admin-infolist-color__swatch {
  width: 1.25em;
  height: 1.25em;
  border-radius: var(--uid-radius-sm);
  border: 1px solid var(--uid-border-subtle);
  flex: none;
}
.admin-infolist-color__value {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}
</style>

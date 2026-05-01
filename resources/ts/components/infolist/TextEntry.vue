<script setup lang="ts">
/**
 * TextEntry — строковое read-only значение.
 *
 * Источник: prop.value либо record[name].
 * Для empty-state — placeholder («—» по умолчанию).
 */
import { computed } from 'vue'
import { tryUseRecord } from './recordContext'

interface Props {
  name?: string
  label?: string
  value?: string | number | null
  /** Заместитель пустого значения. */
  placeholder?: string
  /** Применить mono-font (например для ID/UUID). */
  mono?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  placeholder: '—',
  mono: false,
})

const record = tryUseRecord()
const display = computed<string>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = record[props.name]
  }
  if (v === null || v === undefined || v === '') return props.placeholder
  return String(v)
})
</script>

<template>
  <span :class="['admin-infolist-text', mono ? 'admin-infolist-text--mono' : '']">
    {{ display }}
  </span>
</template>

<style>
.admin-infolist-text {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
}
.admin-infolist-text--mono {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}
</style>

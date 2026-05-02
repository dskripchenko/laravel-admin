<script setup lang="ts">
/**
 * TextEntry — строковое read-only значение со встроенным label.
 *
 * Render-режим:
 *   - если label задан → row с label сверху и value снизу (admin-form display).
 *   - если label пустой → inline span (для обёрток типа BadgeEntry).
 *
 * Auto-formatting через formatCell (см. resource/cellFormat.ts) — преобразует
 * ISO datetime в d.m.Y H:i:s и т.д., если preset указан.
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

const hasLabel = computed<boolean>(() => Boolean(props.label))
</script>

<template>
  <div v-if="hasLabel" class="admin-infolist-entry">
    <span class="admin-infolist-entry__label">{{ label }}</span>
    <span :class="['admin-infolist-entry__value', mono ? 'admin-infolist-entry__value--mono' : '']">
      {{ display }}
    </span>
  </div>
  <span
    v-else
    :class="['admin-infolist-text', mono ? 'admin-infolist-text--mono' : '']"
  >
    {{ display }}
  </span>
</template>

<style>
.admin-infolist-entry {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-2xs);
  padding: var(--uid-space-sm) 0;
}
.admin-infolist-entry + .admin-infolist-entry {
  border-top: 1px solid var(--uid-border-subtle);
}
.admin-infolist-entry__label {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: var(--uid-font-weight-medium);
}
.admin-infolist-entry__value {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
  word-break: break-word;
  white-space: pre-wrap;
}
.admin-infolist-entry__value--mono {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}

.admin-infolist-text {
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
}
.admin-infolist-text--mono {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}
</style>

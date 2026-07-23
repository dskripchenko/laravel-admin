<script setup lang="ts">
/**
 * BadgeEntry — строковое значение в виде UidBadge с variant'ом по value-mapping.
 *
 * Manifest:
 *   { type: 'badge', name: 'status', label: 'Status',
 *     map: { published: 'success', draft: 'warning', archived: 'danger' } }
 */
import { computed } from 'vue'
import { UidBadge } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info'

interface Props {
  name?: string
  label?: string
  value?: string | null
  /** map value → variant. Если не задан — default. */
  map?: Record<string, BadgeVariant>
  /** Backend BadgeEntry::colors() — value → variant (алиас map). */
  colors?: Record<string, BadgeVariant>
  /** map value → отображаемая подпись (локализация: active → «Активен»). */
  labels?: Record<string, string>
  /** Принудительный variant. */
  variant?: BadgeVariant
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  map: () => ({}),
  colors: () => ({}),
  labels: () => ({}),
  variant: undefined,
})

const record = tryUseRecord()
const value = computed<string>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = record[props.name]
  }
  return v === null || v === undefined ? '' : String(v)
})
const variantMap = computed<Record<string, BadgeVariant>>(() => ({
  ...props.map,
  ...props.colors,
}))
const resolvedVariant = computed<BadgeVariant>(() => {
  if (props.variant) return props.variant
  return variantMap.value[value.value] ?? 'default'
})
const displayLabel = computed<string>(() => props.labels[value.value] ?? value.value)
</script>

<template>
  <UidBadge v-if="value" :variant="resolvedVariant">
    {{ displayLabel }}
  </UidBadge>
  <span v-else class="admin-infolist-text">—</span>
</template>

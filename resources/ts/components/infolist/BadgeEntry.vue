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
  /** Принудительный variant. */
  variant?: BadgeVariant
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  map: () => ({}),
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
const resolvedVariant = computed<BadgeVariant>(() => {
  if (props.variant) return props.variant
  return props.map[value.value] ?? 'default'
})
</script>

<template>
  <UidBadge v-if="value" :variant="resolvedVariant">
    {{ value }}
  </UidBadge>
  <span v-else class="admin-infolist-text">—</span>
</template>

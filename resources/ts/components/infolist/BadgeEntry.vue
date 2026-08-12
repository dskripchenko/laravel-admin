<script setup lang="ts">
/**
 * BadgeEntry — a string value drawn as a UidBadge, its variant chosen by a
 * value mapping.
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
  /** Maps a value to a variant; without it, the default is used. */
  map?: Record<string, BadgeVariant>
  /** The backend's BadgeEntry::colors(): value → variant, an alias of map. */
  colors?: Record<string, BadgeVariant>
  /** Maps a value to the label shown — the localization: active → "Active". */
  labels?: Record<string, string>
  /** Forces a particular variant. */
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

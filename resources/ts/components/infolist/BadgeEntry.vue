<script setup lang="ts">
/**
 * BadgeEntry — a string value drawn as a UidBadge, its variant chosen by a
 * value mapping.
 *
 * Manifest:
 *   { type: 'badge', name: 'status', label: 'Status',
 *     map: { published: 'success', draft: 'amber', archived: 'gray' } }
 *
 * Variants and colours come from the panel's tone vocabulary (../tones.ts).
 */
import { computed } from 'vue'
import { UidBadge } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'
import { badgeVariant, type BadgeVariant } from '../tones'

interface Props {
  name?: string
  label?: string
  value?: string | null
  /** Maps a value to a variant; without it, the default is used. */
  map?: Record<string, string>
  /** The backend's BadgeEntry::colors(): value → variant, an alias of map. */
  colors?: Record<string, string>
  /** Maps a value to the label shown — the localization: active → "Active". */
  labels?: Record<string, string>
  /** Forces a particular variant. */
  variant?: string
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
const variantMap = computed<Record<string, string>>(() => ({
  ...props.map,
  ...props.colors,
}))
const resolvedVariant = computed<BadgeVariant>(() => {
  // Variant names and colour words alike — the panel's one tone vocabulary.
  if (props.variant) return badgeVariant(props.variant)
  return badgeVariant(variantMap.value[value.value])
})
const displayLabel = computed<string>(() => props.labels[value.value] ?? value.value)
</script>

<template>
  <UidBadge v-if="value" :variant="resolvedVariant">
    {{ displayLabel }}
  </UidBadge>
  <span v-else class="admin-infolist-text">—</span>
</template>

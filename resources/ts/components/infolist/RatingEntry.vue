<script setup lang="ts">
/**
 * RatingEntry — the view of a Rating field: read-only stars (or the field's
 * icon) out of `count`.
 */
import { computed } from 'vue'
import * as LucideIcons from 'lucide-vue-next'
import { UidRating } from '@dskripchenko/ui'
import { useEntryValue, isEmpty } from './entryValue'

interface Props {
  name?: string
  label?: string
  value?: number | string | null
  count?: number
  half?: boolean
  icon?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  count: 5,
  half: false,
  icon: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const rating = computed<number | null>(() => {
  if (isEmpty(raw.value)) return null
  const n = Number(raw.value)
  return Number.isFinite(n) ? n : null
})
const iconComponent = computed<unknown>(() => {
  if (!props.icon) return undefined
  const key = props.icon.split('-').map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join('')
  return (LucideIcons as Record<string, unknown>)[key]
})
</script>

<template>
  <span v-if="rating === null" class="admin-infolist-text">{{ placeholder }}</span>
  <span v-else class="admin-infolist-rating" :title="`${rating} / ${count}`">
    <UidRating
      :model-value="rating"
      :max="count"
      :allow-half="half"
      :icon="iconComponent"
      :label="label || name"
      readonly
      size="sm"
    />
  </span>
</template>

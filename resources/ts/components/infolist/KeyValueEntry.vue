<script setup lang="ts">
/**
 * KeyValueEntry — словарь {key: value} как UidDescriptionList.
 * Source: prop.value (Record) либо record[name].
 */
import { computed } from 'vue'
import { UidDescriptionList, UidDescriptionItem } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'

interface Props {
  name?: string
  label?: string
  value?: Record<string, unknown>
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
})

const record = tryUseRecord()
const items = computed<Array<[string, unknown]>>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = record[props.name]
  }
  if (v === null || v === undefined) return []
  if (typeof v !== 'object') return []
  return Object.entries(v as Record<string, unknown>)
})

function formatValue(v: unknown): string {
  if (v === null || v === undefined) return '—'
  // Booleans (and 0/1 placeholders for them) read as "Да" / "Нет" so a
  // permission-style {slug: true} map renders as a clean list of
  // allowed items, not literal "true" tokens.
  if (typeof v === 'boolean') return v ? 'Да' : 'Нет'
  if (v === 0) return 'Нет'
  if (v === 1) return 'Да'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}
</script>

<template>
  <UidDescriptionList v-if="items.length > 0" direction="vertical" :columns="1">
    <UidDescriptionItem
      v-for="[k, v] in items"
      :key="k"
      :label="k"
    >
      {{ formatValue(v) }}
    </UidDescriptionItem>
  </UidDescriptionList>
  <span v-else class="admin-infolist-text">—</span>
</template>

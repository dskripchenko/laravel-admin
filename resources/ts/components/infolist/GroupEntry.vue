<script setup lang="ts">
/**
 * GroupEntry — the view of a Group field: its nested fields, each through
 * FieldEntry, read from the group's own object (`record.address.city`).
 */
import { computed } from 'vue'
import { provideRecord, tryUseRecord } from './recordContext'
import InfolistRenderer, { type InfolistNode } from './InfolistRenderer.vue'

interface ChildField {
  type: string
  name: string
  label?: string | null
  [key: string]: unknown
}

interface Props {
  name?: string
  value?: unknown
  fields?: ChildField[]
  layout?: 'rows' | 'columns' | 'inline'
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  fields: () => [],
  layout: 'rows',
})

const parent = tryUseRecord()
const scope = (): Record<string, unknown> => {
  const v = props.value !== undefined ? props.value : parent?.[props.name]
  return v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : {}
}

// A live view: reads go through the parent record, so a reloaded record shows
// through without re-providing.
provideRecord(
  new Proxy({} as Record<string, unknown>, {
    get: (_t, key) => (typeof key === 'string' ? scope()[key] : undefined),
    has: (_t, key) => typeof key === 'string' && key in scope(),
  }),
)

const nodes = computed<InfolistNode[]>(() =>
  props.fields
    .filter((f) => f.type !== 'hidden')
    .map((f) => ({
      kind: 'entry',
      type: 'field',
      name: f.name,
      label: f.label ?? f.name,
      attributes: { field: f },
    })),
)
</script>

<template>
  <div class="admin-infolist-group" :class="`admin-infolist-group--${layout}`">
    <InfolistRenderer v-for="n in nodes" :key="n.name as string" :node="n" />
  </div>
</template>

<style>
.admin-infolist-group {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding-left: var(--uid-space-md, 12px);
  border-left: 2px solid var(--uid-border-subtle);
}
.admin-infolist-group--columns,
.admin-infolist-group--inline {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0 var(--uid-space-md, 12px);
}
</style>

<script setup lang="ts">
/**
 * RelationEntry — a related record (the backend's Infolist\RelationEntry).
 *
 * The value is the loaded relation in the record — `record[relation]`, also
 * under its snake_case key, the way Eloquent's toArray() writes it — or the
 * entry's own field. An object shows its `->display()` column (`name` by
 * default); a list shows every item. With `->linkTo('users')` and an id, each
 * item links to that resource's view page; otherwise it is plain text.
 */
import { computed } from 'vue'
import { UidLink } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'
import { useEntryValue } from './entryValue'

interface Props {
  name?: string
  value?: unknown
  relation?: string | null
  displayColumn?: string | null
  linkTo?: string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  relation: null,
  displayColumn: null,
  linkTo: null,
  placeholder: '—',
})

const record = tryUseRecord()
const own = useEntryValue(props)

const snake = (s: string): string => s.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase()

const raw = computed<unknown>(() => {
  if (props.value !== undefined) return props.value
  if (props.relation && record) {
    const v = record[props.relation] ?? record[snake(props.relation)]
    if (v !== undefined && v !== null) return v
  }
  return own.value
})

interface Item {
  label: string
  id: string | number | null
}

function toItem(v: unknown): Item | null {
  if (v === null || v === undefined || v === '') return null
  if (typeof v !== 'object') {
    // A scalar: the foreign key itself (author_id) — its value is the id.
    const id = typeof v === 'number' || typeof v === 'string' ? v : null
    return { label: String(v), id }
  }
  const r = v as Record<string, unknown>
  const col = props.displayColumn ?? 'name'
  const shown = r[col] ?? r.name ?? r.title ?? r.label ?? r.email ?? r.id
  const id = typeof r.id === 'number' || typeof r.id === 'string' ? r.id : null
  return { label: shown === undefined || shown === null ? '' : String(shown), id }
}

const items = computed<Item[]>(() => {
  const v = raw.value
  const list = Array.isArray(v) ? v : [v]
  return list.map(toItem).filter((i): i is Item => i !== null && i.label !== '')
})

const linkOf = (item: Item): string | null =>
  props.linkTo && item.id !== null && item.id !== '' ? `/r/${props.linkTo}/${encodeURIComponent(String(item.id))}` : null
</script>

<template>
  <span v-if="items.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <span v-else class="admin-infolist-relation">
    <template v-for="(item, idx) in items" :key="idx">
      <UidLink v-if="linkOf(item)" :to="linkOf(item) ?? undefined" class="admin-infolist-relation__item">{{ item.label }}</UidLink>
      <span v-else class="admin-infolist-relation__item">{{ item.label }}</span>
    </template>
  </span>
</template>

<style>
.admin-infolist-relation {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--uid-space-2xs, 2px) var(--uid-space-sm, 8px);
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
}
</style>

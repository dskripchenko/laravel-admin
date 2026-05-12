<script setup lang="ts">
/**
 * RepeatableEntry — read-only коллекция (parный к Field\Repeater).
 *
 * Backend RepeatableEntry::make('key')->entries([
 *   TextEntry::make('field_a')->label('A'),
 *   BadgeEntry::make('flag')->label('Flag')->map([...]),
 * ])->layout('columns')
 *
 * Props (после spread'а из FieldRenderer):
 *   - name: string — ключ в record
 *   - label?: string
 *   - entries: Entry[] — список sub-entry-метаданных
 *   - layout?: 'columns' | 'rows' | 'inline' (default 'columns')
 *   - value?: array (если передан напрямую, иначе берётся из record[name])
 *
 * Рендер:
 *   - 'columns' — html <table> с колонками по entries[]
 *   - 'rows'    — каждый item card, внутри пары label/value
 *   - 'inline'  — chip-list через запятую
 */
import { computed } from 'vue'
import { tryUseRecord } from './recordContext'
import { getInfolistEntry } from './registry'

interface EntryMeta extends Record<string, unknown> {
  type: string
  name: string
  label?: string
  attributes?: Record<string, unknown>
}

interface Props {
  name?: string
  label?: string
  entries?: EntryMeta[]
  layout?: 'columns' | 'rows' | 'inline'
  value?: unknown
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  entries: () => [],
  layout: 'columns',
  value: undefined,
  placeholder: '—',
})

const record = tryUseRecord()

const items = computed<Array<Record<string, unknown>>>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = (record as Record<string, unknown>)[props.name]
  }
  if (typeof v === 'string') {
    try { v = JSON.parse(v) } catch { v = [] }
  }
  if (!Array.isArray(v)) return []
  return v.filter((x): x is Record<string, unknown> => x !== null && typeof x === 'object')
})

function entryLabel(e: EntryMeta): string {
  return String(e.label ?? e.name ?? '')
}

function resolveComponent(type: string) {
  return getInfolistEntry(type)
}

function subProps(entry: EntryMeta, item: Record<string, unknown>) {
  // Развернём attributes (как FieldRenderer): чтобы preset/meta/map дошли
  // до sub-component'а на верхнем уровне props.
  const { type: _type, ...rest } = entry
  const attrs = (entry.attributes as Record<string, unknown> | undefined) ?? {}
  return {
    ...rest,
    ...attrs,
    label: '',           // лейбл уже в шапке таблицы
    value: item[entry.name],
    name: entry.name,
  }
}
</script>

<template>
  <div class="admin-repeatable">
    <span v-if="label" class="admin-repeatable__lbl">{{ label }}</span>

    <p v-if="items.length === 0" class="admin-repeatable__empty">
      {{ placeholder }}
    </p>

    <!-- columns: классическая таблица -->
    <table
      v-else-if="layout === 'columns'"
      class="admin-repeatable__tbl"
    >
      <thead>
        <tr>
          <th class="admin-repeatable__idx">#</th>
          <th v-for="e in entries" :key="e.name">{{ entryLabel(e) }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, idx) in items" :key="idx">
          <td class="admin-repeatable__idx">{{ idx + 1 }}</td>
          <td v-for="e in entries" :key="e.name">
            <component
              :is="resolveComponent(e.type) || 'span'"
              v-bind="subProps(e, item)"
            />
          </td>
        </tr>
      </tbody>
    </table>

    <!-- rows: cards, каждый item — отдельный блок rows -->
    <div
      v-else-if="layout === 'rows'"
      class="admin-repeatable__cards"
    >
      <article
        v-for="(item, idx) in items"
        :key="idx"
        class="admin-repeatable__card"
      >
        <header class="admin-repeatable__card-hd">#{{ idx + 1 }}</header>
        <dl class="admin-repeatable__dl">
          <template v-for="e in entries" :key="e.name">
            <dt>{{ entryLabel(e) }}</dt>
            <dd>
              <component
                :is="resolveComponent(e.type) || 'span'"
                v-bind="subProps(e, item)"
              />
            </dd>
          </template>
        </dl>
      </article>
    </div>

    <!-- inline: chip-list -->
    <div
      v-else
      class="admin-repeatable__inline"
    >
      <span
        v-for="(item, idx) in items"
        :key="idx"
        class="admin-repeatable__chip"
      >
        <template v-for="e in entries" :key="e.name">
          <component
            :is="resolveComponent(e.type) || 'span'"
            v-bind="subProps(e, item)"
          />
        </template>
      </span>
    </div>
  </div>
</template>

<style>
.admin-repeatable { display: flex; flex-direction: column; gap: 8px; padding: 8px 0; }
.admin-repeatable__lbl {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: var(--uid-font-weight-medium);
}
.admin-repeatable__empty {
  color: var(--uid-text-tertiary);
  font-size: var(--uid-font-size-sm);
  margin: 0;
}

.admin-repeatable__tbl {
  border-collapse: collapse;
  width: 100%;
  font-size: var(--uid-font-size-sm);
}
.admin-repeatable__tbl th,
.admin-repeatable__tbl td {
  border: 1px solid var(--uid-border-subtle, #e5e7eb);
  padding: 6px 10px;
  text-align: left;
  vertical-align: top;
}
.admin-repeatable__tbl th {
  background: var(--uid-surface-muted, #f9fafb);
  font-weight: var(--uid-font-weight-semibold, 600);
  color: var(--uid-text-secondary, #4b5563);
  font-size: var(--uid-font-size-xs, 12px);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.admin-repeatable__idx {
  width: 40px;
  color: var(--uid-text-tertiary, #6b7280);
  text-align: center !important;
}

.admin-repeatable__cards { display: flex; flex-direction: column; gap: 8px; }
.admin-repeatable__card {
  border: 1px solid var(--uid-border-subtle, #e5e7eb);
  border-radius: 6px;
  padding: 10px 14px;
}
.admin-repeatable__card-hd {
  font-size: 12px;
  font-weight: 600;
  color: var(--uid-text-tertiary, #6b7280);
  margin-bottom: 4px;
}
.admin-repeatable__dl {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 4px 12px;
  margin: 0;
}
.admin-repeatable__dl dt {
  color: var(--uid-text-tertiary, #6b7280);
  font-size: 12px;
}
.admin-repeatable__dl dd {
  margin: 0;
  font-size: 13px;
}

.admin-repeatable__inline { display: flex; flex-wrap: wrap; gap: 6px; }
.admin-repeatable__chip {
  padding: 3px 10px;
  border: 1px solid var(--uid-border-subtle, #e5e7eb);
  border-radius: 999px;
  font-size: 12px;
}
</style>

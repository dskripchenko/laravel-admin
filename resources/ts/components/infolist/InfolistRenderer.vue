<script setup lang="ts">
/**
 * InfolistRenderer — read-only аналог LayoutRenderer для view-страниц.
 *
 * Узлы:
 *   { type: 'text', name: 'title', label: 'Заголовок' }   — entry (leaf)
 *   { type: 'rows', items: [ ... ] }                       — layout (recurse)
 *   { type: 'section', title: 'X', items: [ ... ] }        — layout
 *   { type: 'columns', items: [ ... ] }                    — layout
 *
 * Внимание: layout-узлы рендерятся СВОИМ внутренним wrapper'ом, а не
 * registered uid-layouts из render/registry, потому что те делегируют детей
 * в LayoutRenderer (field-registry → useFormState ОБЯЗАТЕЛЕН). Здесь record-
 * read-only режим — детей рендерим через сам InfolistRenderer.
 */
import { computed } from 'vue'
import { getInfolistEntry } from './registry'
import UnknownEntry from './UnknownEntry.vue'

export interface InfolistNode extends Record<string, unknown> {
  type: string
  /** Hint: 'entry' либо 'layout'. */
  kind?: 'entry' | 'layout'
  items?: InfolistNode[]
}

interface Props {
  node: InfolistNode
}
const props = defineProps<Props>()

const KNOWN_LAYOUTS = new Set(['rows', 'columns', 'section', 'block', 'tabs'])

type Resolved =
  | { kind: 'entry'; component: NonNullable<ReturnType<typeof getInfolistEntry>> }
  | { kind: 'layout'; layoutType: string }
  | { kind: 'unknown' }

const resolved = computed<Resolved>(() => {
  if (props.node.kind === 'entry') {
    const c = getInfolistEntry(props.node.type)
    return c ? { kind: 'entry', component: c } : { kind: 'unknown' }
  }
  if (props.node.kind === 'layout') {
    return KNOWN_LAYOUTS.has(props.node.type)
      ? { kind: 'layout', layoutType: props.node.type }
      : { kind: 'unknown' }
  }
  // Auto-detect: layouts по имени, entries по registry.
  if (KNOWN_LAYOUTS.has(props.node.type)) {
    return { kind: 'layout', layoutType: props.node.type }
  }
  const entry = getInfolistEntry(props.node.type)
  if (entry) return { kind: 'entry', component: entry }
  return { kind: 'unknown' }
})

const entryProps = computed(() => {
  const { type: _t, kind: _k, items: _i, attributes, ...rest } = props.node
  // Backend кладёт preset/format/etc в `attributes` под-объект (см.
  // Infolist\Entry::toArray). Flat'имся для удобства Vue v-bind:
  // attributes.preset → preset prop, attributes.format → meta.format.
  const attrs = (attributes as Record<string, unknown> | undefined) ?? {}
  const { preset, format, currency, decimals, trueLabel, falseLabel, ...rest2 } = attrs
  const meta: Record<string, unknown> = {}
  if (format !== undefined) meta.format = format
  if (currency !== undefined) meta.currency = currency
  if (decimals !== undefined) meta.decimals = decimals
  if (trueLabel !== undefined) meta.trueLabel = trueLabel
  if (falseLabel !== undefined) meta.falseLabel = falseLabel
  return { ...rest, ...rest2, preset, meta }
})

const items = computed<InfolistNode[]>(
  () => (props.node.items ?? []) as InfolistNode[],
)

const sectionTitle = computed(() => (props.node.title as string | undefined) ?? null)
const sectionDescription = computed(
  () => (props.node.description as string | undefined) ?? null,
)
const tabsActive = computed<number>(() => {
  const a = props.node.active
  return typeof a === 'number' ? a : 0
})

interface InfolistTab {
  key?: string
  label: string
  items: InfolistNode[]
}
const tabsList = computed<InfolistTab[]>(() => {
  return (props.node.items ?? []) as unknown as InfolistTab[]
})
</script>

<template>
  <!-- Entry -->
  <component
    :is="resolved.component"
    v-if="resolved.kind === 'entry'"
    v-bind="entryProps"
  />

  <!-- Layout: rows -->
  <div
    v-else-if="resolved.kind === 'layout' && resolved.layoutType === 'rows'"
    class="admin-infolist-rows"
  >
    <InfolistRenderer
      v-for="(child, idx) in items"
      :key="idx"
      :node="child"
    />
  </div>

  <!-- Layout: columns (12-grid с per-item span) -->
  <div
    v-else-if="resolved.kind === 'layout' && resolved.layoutType === 'columns'"
    class="admin-infolist-cols"
  >
    <div
      v-for="(child, idx) in items"
      :key="idx"
      class="admin-infolist-cols__item"
      :style="{
        gridColumn: `span ${
          typeof (child as Record<string, unknown>).span === 'number'
            ? (child as Record<string, unknown>).span
            : Math.max(1, Math.floor(12 / items.length))
        }`,
      }"
    >
      <InfolistRenderer :node="child" />
    </div>
  </div>

  <!-- Layout: section / block -->
  <section
    v-else-if="resolved.kind === 'layout' && (resolved.layoutType === 'section' || resolved.layoutType === 'block')"
    class="admin-infolist-section"
  >
    <header v-if="sectionTitle || sectionDescription" class="admin-infolist-section__hd">
      <h3 v-if="sectionTitle" class="admin-infolist-section__title">{{ sectionTitle }}</h3>
      <p v-if="sectionDescription" class="admin-infolist-section__description">
        {{ sectionDescription }}
      </p>
    </header>
    <div class="admin-infolist-section__body">
      <InfolistRenderer
        v-for="(child, idx) in items"
        :key="idx"
        :node="child"
      />
    </div>
  </section>

  <!-- Layout: tabs (минимальные — без переключения, показываем active по prop'у) -->
  <div
    v-else-if="resolved.kind === 'layout' && resolved.layoutType === 'tabs'"
    class="admin-infolist-tabs"
  >
    <ul class="admin-infolist-tabs__list" role="tablist">
      <li
        v-for="(tab, idx) in tabsList"
        :key="tab.key ?? idx"
        :class="[
          'admin-infolist-tabs__tab',
          { 'admin-infolist-tabs__tab--active': idx === tabsActive },
        ]"
      >
        {{ tab.label }}
      </li>
    </ul>
    <div class="admin-infolist-tabs__panel">
      <InfolistRenderer
        v-for="(child, idx) in tabsList[tabsActive]?.items ?? []"
        :key="idx"
        :node="child"
      />
    </div>
  </div>

  <UnknownEntry v-else :type="node.type" :name="(node.name as string | undefined)" />
</template>

<style>
.admin-infolist-rows {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-infolist-cols {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--uid-space-md);
}
.admin-infolist-section {
  margin-bottom: var(--uid-space-md);
  padding: var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-lg);
  background: var(--uid-surface-raised);
}
.admin-infolist-section__hd { margin-bottom: var(--uid-space-sm); }
.admin-infolist-section__title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-infolist-section__description {
  margin: var(--uid-space-2xs) 0 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-infolist-section__body {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-infolist-tabs__list {
  list-style: none;
  margin: 0 0 var(--uid-space-sm);
  padding: 0;
  display: flex;
  gap: var(--uid-space-xs);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-infolist-tabs__tab {
  padding: var(--uid-space-sm) var(--uid-space-sm);
  font-size: 13px;
  color: var(--uid-text-secondary);
  border-bottom: 2px solid transparent;
}
.admin-infolist-tabs__tab--active {
  color: var(--uid-text-primary);
  border-bottom-color: var(--uid-accent);
  font-weight: var(--uid-font-weight-medium);
}
</style>

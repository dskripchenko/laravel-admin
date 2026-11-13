<script setup lang="ts">
/**
 * TabsLayout, built on UidTabs, UidTab and UidTabPanel.
 * The active tab is bound both ways through v-model:active.
 *
 * A tab that is not open is not mounted (UidTabPanel renders the active one
 * only), so a list in it loads nothing until it is opened.
 *
 * `queryKey` (Tabs::persistInQuery()) keeps the open tab in the address —
 * `?tab=quotes` — so a link leads to a tab, and back and forward follow it.
 * `orientation: 'vertical'` (Tabs::vertical()) puts the tabs in a column on
 * the left: a list of dictionaries beside the open one.
 */
import { computed, inject, onBeforeUnmount, provide, ref, watch, type InjectionKey } from 'vue'
import { formatBadge } from '../../stores/menu'
import { useUserNavStore } from '../../stores/userNav'
import { testIdOf } from '../../composables/testId'
import { routeLocationKey, routerKey } from 'vue-router'
import { patchRouteQuery } from '../../composables/useQueryPatch'
import { UidTabs, UidTab, UidTabPanel, UidStack } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

export interface TabNode {
  /** The tab's unique key; without one, its index is used. */
  key?: string
  /** The tab's text. */
  label: string
  icon?: string | null
  items: LayoutNode[]
  /** A counter or a short text beside the label (Tabs::badges()). */
  badge?: string | number | null
  /** 'danger' paints the badge red. */
  badgeTone?: string | null
}

interface Props {
  items: TabNode[]
  /** The active tab — its key, or its index when there is no key. */
  active?: string | number
  /**
   * The gap between the elements inside a tab.
   *
   * A tab panel used to lay its children out with NO stack: the fields ran
   * together at a gap of zero. Inside `Rows` the very same fields breathed
   * (`--uid-space-md`), while on a tab the next field's label stuck to the
   * previous one's hint — it read as one continuous ribbon, with no telling
   * where one field ended and the next began.
   *
   * The value is one step above the line rhythm: a tab is a set of sets, and
   * the distance between them should be more noticeable than the distance
   * inside a single field.
   */
  gap?: string
  /** The index of the tab open at first (Tabs::defaultTab()). */
  default?: number | null
  /** The URL key the open tab is kept under (Tabs::persistInQuery()); null — kept locally. */
  queryKey?: string | null
  /** 'horizontal' (a row above) or 'vertical' (a column on the left). */
  orientation?: 'horizontal' | 'vertical'
}

const props = withDefaults(defineProps<Props>(), {
  active: undefined,
  gap: 'var(--uid-space-lg)',
  default: null,
  queryKey: null,
  orientation: 'horizontal',
})
const emit = defineEmits<{ 'update:active': [value: string | number] }>()

const router = inject(routerKey, null)
const route = inject(routeLocationKey, null)

function tabKey(tab: TabNode, idx: number): string | number {
  return tab.key ?? idx
}

/** A tab's value from an index or a key — the address carries either as a string. */
function resolveTab(value: unknown): string | number | null {
  if (value === null || value === undefined || value === '') return null
  for (const [idx, tab] of props.items.entries()) {
    const key = tabKey(tab, idx)
    if (key === value || String(key) === String(value)) return key
  }
  if (typeof value === 'number' && props.items[value]) return tabKey(props.items[value], value)
  return null
}

function fromQuery(): string | number | null {
  if (!props.queryKey || !route) return null
  const raw = route.query[props.queryKey]
  return resolveTab(Array.isArray(raw) ? raw[0] : raw)
}

const firstTab = (): string | number => tabKey(props.items[0] ?? { label: '', items: [] }, 0)

const localActive = ref<string | number>(
  fromQuery() ?? resolveTab(props.active) ?? resolveTab(props.default) ?? firstTab(),
)

function onUpdate(value: string | number): void {
  localActive.value = value
  emit('update:active', value)
  if (props.queryKey && router) {
    const key = props.queryKey
    patchRouteQuery(router, (query) => {
      query[key] = String(value)
    })
  }
}

/*
 * The open tab of a tab set kept in the address is the page's own: the
 * breadcrumbs name it («Заявки › CRM») and the favourites' star keeps it. A
 * nested set comes after its parent.
 */
const TABS_DEPTH: InjectionKey<number> = Symbol.for('admin-tabs-depth')
const depth = inject(TABS_DEPTH, 0)
provide(TABS_DEPTH, depth + 1)
// Outside an app with Pinia (a bare mount) the tabs simply are not reported.
const userNav = (() => {
  try {
    return useUserNavStore()
  } catch {
    return null
  }
})()
const activeLabel = computed(() => {
  for (const [idx, tab] of props.items.entries()) {
    if (tabKey(tab, idx) === localActive.value) return tab.label
  }
  return null
})
watch(
  activeLabel,
  (label) => {
    if (!props.queryKey || !userNav) return
    if (label === null) userNav.unregisterTab(props.queryKey)
    else userNav.registerTab({ queryKey: props.queryKey, label, depth })
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  if (props.queryKey) userNav?.unregisterTab(props.queryKey)
})

// A link to another tab of the same screen, back and forward.
watch(
  () => (props.queryKey && route ? route.query[props.queryKey] : undefined),
  () => {
    const next = fromQuery()
    if (next !== null && next !== localActive.value) localActive.value = next
  },
)
</script>

<template>
  <UidTabs :model-value="localActive" :orientation="orientation" @update:model-value="onUpdate">
    <template #list>
      <UidTab
        v-for="(tab, idx) in items"
        :key="tabKey(tab, idx)"
        :value="tabKey(tab, idx)"
        :data-testid="testIdOf('tab', tabKey(tab, idx))"
      >
        {{ tab.label }}
        <span
          v-if="formatBadge(tab.badge) !== null"
          class="admin-tab-badge"
          :class="{ 'admin-tab-badge--danger': tab.badgeTone === 'danger' }"
          :data-testid="testIdOf('tab-badge', tabKey(tab, idx))"
        >{{ formatBadge(tab.badge) }}</span>
      </UidTab>
    </template>

    <UidTabPanel
      v-for="(tab, idx) in items"
      :key="`panel-${tabKey(tab, idx)}`"
      :value="tabKey(tab, idx)"
    >
      <UidStack direction="column" :gap="gap" align="stretch" :data-testid="testIdOf('tab-panel', tabKey(tab, idx))">
        <LayoutRenderer
          v-for="(child, cidx) in tab.items"
          :key="cidx"
          :node="child"
        />
      </UidStack>
    </UidTabPanel>
  </UidTabs>
</template>

<style>
/* A tab's counter: a queue's size beside its name. */
.admin-tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  margin-inline-start: 6px;
  padding: 0 6px;
  border-radius: 9px;
  background: var(--uid-border-subtle);
  color: var(--uid-text-secondary);
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
}
.admin-tab-badge--danger {
  background: var(--uid-color-danger-solid, #dc2626);
  color: var(--uid-color-text-on-primary, #fff);
}

/* Vertical tabs: a column of names on the left, the open tab beside it. A
   table in the panel must be able to shrink, and the names keep to a width. */
.uid-tabs--vertical > .uid-tabs__panels {
  min-width: 0;
}
.uid-tabs--vertical > .uid-tabs__list {
  flex: 0 0 auto;
  max-width: 240px;
}
@media (max-width: 720px) {
  .uid-tabs--vertical {
    flex-direction: column;
  }
  .uid-tabs--vertical > .uid-tabs__list {
    max-width: none;
  }
}
</style>

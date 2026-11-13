<script setup lang="ts">
/**
 * GlobalSearch — the ⌘K (Ctrl+K) command palette. It shows:
 *   - with an empty query: the user's favourites and recent pages;
 *   - the sections, for jumping anywhere in the panel: the menu's items and
 *     the tabs of its screens (`/system/menuTabs`, loaded once), matched by
 *     the label, the menu path and the tab's key — instantly, offline;
 *   - the actions: the «+ Create» items (MenuRegistry::quickCreate());
 *   - the records, from the server: a debounced GET /system/search?q= across
 *     the resources' searchable fields and the host's SearchProviders. A group
 *     whose provider recognized the input (a policy number, a phone, an
 *     e-mail) goes above everything else.
 *
 * The keyboard: ↑/↓ navigate, Enter goes, Esc closes the UidModal. It opens on
 * ⌘K / Ctrl+K, from AdminShell, or on a click of the topbar's search pill.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { UidIcon, UidModal, UidSpinner } from '@dskripchenko/ui'
import { Search, CornerDownLeft } from 'lucide-vue-next'
import { trSafe as tr } from '../../stores/i18n'
import { getAdminClient } from '../../stores/registry'
import { useMenuStore, type MenuItem } from '../../stores/menu'
import { useUserNavStore } from '../../stores/userNav'
import { testIdOf } from '../../composables/testId'
import { useI18nStore } from '../../stores/i18n'
import { resolveIcon } from './iconRegistry'

interface Props {
  modelValue: boolean
}
const props = defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

interface Hit {
  title: string
  subtitle: string | null
  url: string
}
interface Group {
  /** A stable name of the group, for its data-testid: favorites, recent, sections, actions, or the server's slug. */
  key?: string
  label: string
  icon: string | null
  items: Hit[]
  hasMore?: boolean
  moreUrl?: string | null
  /** A host's provider recognized the query: the group goes on top. */
  recognized?: boolean
}

/** A tab of a screen in the user's menu (`/system/menuTabs`). */
interface MenuTab {
  key: string
  label: string
  screen: string
  path: string[]
  url: string
  keywords?: string[]
}

/** The menu's screens' tabs, loaded once per page load. */
let menuTabsCache: MenuTab[] | null = null
let menuTabsLoading: Promise<void> | null = null

const router = useRouter()
const menu = useMenuStore()
const userNav = useUserNavStore()
const menuTabs = ref<MenuTab[]>(menuTabsCache ?? [])

function loadMenuTabs(): void {
  if (menuTabsCache !== null) {
    menuTabs.value = menuTabsCache
    return
  }
  menuTabsLoading ??= getAdminClient()
    .get<{ items: MenuTab[] }>('/system/menuTabs')
    .then((res) => {
      menuTabsCache = Array.isArray(res.items) ? res.items : []
    })
    .catch(() => {
      menuTabsLoading = null
    })
  void menuTabsLoading.then(() => {
    if (menuTabsCache !== null) menuTabs.value = menuTabsCache
  })
}
const i18n = useI18nStore()
const t = (k: string, fb: string): string => (i18n.has(k) ? i18n.t(k) : fb)

const query = ref<string>('')
const loading = ref<boolean>(false)
const serverGroups = ref<Group[]>([])
const activeIndex = ref<number>(0)
const inputRef = ref<HTMLInputElement | null>(null)

const open = computed<boolean>({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

/** The menu's items flattened recursively with their path, for the navigation search. */
function flattenMenu(items: MenuItem[], parents: string[] = []): Array<{ item: MenuItem; path: string[] }> {
  const out: Array<{ item: MenuItem; path: string[] }> = []
  for (const it of items) {
    if (it.url) out.push({ item: it, path: parents })
    if (it.children?.length) out.push(...flattenMenu(it.children, [...parents, it.label]))
  }
  return out
}

/** Every word of the query is found in the haystack. */
function matches(haystack: string, q: string): boolean {
  const h = haystack.toLowerCase()
  return q.split(/\s+/).filter((w) => w !== '').every((w) => h.includes(w))
}

const navGroup = computed<Group | null>(() => {
  const q = query.value.trim().toLowerCase()
  if (q.length === 0) return null
  const hits: Hit[] = []
  const seen = new Set<string>()
  for (const { item, path } of flattenMenu(menu.visibleItems)) {
    if (!matches([item.label, ...path].join(' '), q) || seen.has(item.url as string)) continue
    seen.add(item.url as string)
    hits.push({ title: item.label, subtitle: path.length > 0 ? path.join(' › ') : null, url: item.url as string })
  }
  for (const tab of menuTabs.value) {
    if (seen.has(tab.url) || !matches([...tab.path, ...(tab.keywords ?? [])].join(' '), q)) continue
    seen.add(tab.url)
    hits.push({ title: `${tab.screen} › ${tab.label}`, subtitle: tab.path.slice(0, -2).join(' › ') || null, url: tab.url })
  }
  if (hits.length === 0) return null
  return { key: 'sections', label: t('admin.search.sections', 'Разделы'), icon: null, items: hits.slice(0, 8) }
})

/** The «+ Create» items as the palette's actions. */
const actionsGroup = computed<Group | null>(() => {
  const q = query.value.trim().toLowerCase()
  if (q.length === 0) return null
  const verb = tr('Создать').toLowerCase()
  const hits = menu.quickCreate
    .filter((it) => matches(`${verb} ${it.label}`, q))
    .map<Hit>((it) => ({ title: `${tr('Создать')}: ${it.label}`, subtitle: null, url: it.url }))
  return hits.length > 0 ? { key: 'actions', label: tr('Действия'), icon: 'plus', items: hits } : null
})

/** With an empty query: the favourites and the recent pages. */
const emptyGroups = computed<Group[]>(() => {
  const g: Group[] = []
  if (userNav.favorites.length > 0) {
    g.push({
      key: 'favorites',
      label: tr('Избранное'),
      icon: 'star',
      items: userNav.favorites.map((f) => ({ title: f.label, subtitle: null, url: f.url })),
    })
  }
  if (userNav.recent.length > 0) {
    g.push({
      key: 'recent',
      label: tr('Недавнее'),
      icon: 'clock',
      items: userNav.recent.map((r) => ({ title: r.label, subtitle: r.subtitle ?? null, url: r.url })),
    })
  }
  return g
})

/**
 * Every group to render: with an empty query the favourites and the recent
 * pages; else the recognized groups first (a policy number, a phone), then
 * the sections and the actions, then the rest of the records.
 */
const groups = computed<Group[]>(() => {
  if (query.value.trim() === '') return emptyGroups.value
  const recognized = serverGroups.value.filter((g) => g.recognized === true)
  const rest = serverGroups.value.filter((g) => g.recognized !== true)
  const g: Group[] = [...recognized]
  if (navGroup.value) g.push(navGroup.value)
  if (actionsGroup.value) g.push(actionsGroup.value)
  g.push(...rest)
  return g
})

/** The links flattened in display order, for the arrows and Enter. */
const flatHits = computed<Hit[]>(() => groups.value.flatMap((g) => g.items))

const hasQuery = computed<boolean>(() => query.value.trim().length >= 2)
const isEmptyQuery = computed<boolean>(() => query.value.trim() === '')
const showEmpty = computed<boolean>(
  () => hasQuery.value && !loading.value && flatHits.value.length === 0,
)

let debounce: ReturnType<typeof setTimeout> | null = null
let seq = 0

watch(query, (q) => {
  activeIndex.value = 0
  if (debounce) clearTimeout(debounce)
  if (q.trim().length < 2) {
    serverGroups.value = []
    loading.value = false
    return
  }
  loading.value = true
  debounce = setTimeout(() => void runSearch(q), 220)
})

async function runSearch(q: string): Promise<void> {
  const mine = ++seq
  try {
    const res = await getAdminClient().get<{ groups: Array<Group & { slug?: string }> }>('/system/search', {
      params: { q },
    })
    if (mine !== seq) return // устаревший ответ — пришёл новый запрос
    serverGroups.value = Array.isArray(res.groups)
      ? res.groups.map((g) => ({ ...g, key: g.key ?? g.slug ?? g.label }))
      : []
  } catch {
    if (mine === seq) serverGroups.value = []
  } finally {
    if (mine === seq) loading.value = false
  }
}

// An item's index in the flat list, for highlighting the active one.
function indexOfHit(group: Group, item: Hit): number {
  let base = 0
  for (const g of groups.value) {
    if (g === group) break
    base += g.items.length
  }
  return base + group.items.indexOf(item)
}

function select(hit: Hit | undefined): void {
  if (!hit) return
  open.value = false
  if (/^[a-z]+:\/\//i.test(hit.url)) {
    window.open(hit.url, '_blank', 'noopener')
    return
  }
  void router.push(hit.url)
}

function onArrow(delta: number): void {
  const n = flatHits.value.length
  if (n === 0) return
  activeIndex.value = (activeIndex.value + delta + n) % n
}

// Opened from the start (a host's own mount): the tabs are needed at once.
if (props.modelValue) loadMenuTabs()

// Focus and reset when it opens.
watch(open, (isOpen) => {
  if (isOpen) {
    query.value = ''
    serverGroups.value = []
    activeIndex.value = 0
    loadMenuTabs()
    void nextTick(() => inputRef.value?.focus())
  }
})
</script>

<template>
  <UidModal v-model="open" size="md">
    <div class="admin-search__box">
      <UidIcon :icon="Search" :size="16" class="admin-search__box-icon" />
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        autofocus
        class="admin-search__input"
        data-testid="search-input"
        :placeholder="t('admin.search.placeholder', 'Поиск по разделам и записям…')"
        autocomplete="off"
        spellcheck="false"
        @keydown.down.prevent="onArrow(1)"
        @keydown.up.prevent="onArrow(-1)"
        @keydown.enter.prevent="select(flatHits[activeIndex])"
      />
      <UidSpinner v-if="loading" size="sm" />
    </div>

    <div class="admin-search__results">
      <p v-if="isEmptyQuery && groups.length === 0" class="admin-search__hint" data-testid="search-hint">
        {{ t('admin.search.hint_min', 'Введите минимум 2 символа. Ищет по разделам и записям всех ресурсов.') }}
      </p>
      <p v-else-if="!isEmptyQuery && !hasQuery && groups.length === 0" class="admin-search__hint">
        {{ t('admin.search.hint_min', 'Введите минимум 2 символа. Ищет по разделам и записям всех ресурсов.') }}
      </p>
      <p v-else-if="showEmpty" class="admin-search__hint">
        {{ t('admin.search.hint_empty', 'Ничего не найдено по запросу') }} «{{ query }}».
      </p>

      <div
        v-for="group in groups"
        :key="group.key ?? group.label"
        class="admin-search__group"
        :class="{ 'admin-search__group--recognized': group.recognized }"
        :data-testid="testIdOf('search-group', group.key ?? group.label)"
      >
        <div class="admin-search__group-hd">
          <UidIcon v-if="resolveIcon(group.icon)" :icon="resolveIcon(group.icon)!" :size="12" />
          <span>{{ group.label }}</span>
        </div>
        <button
          v-for="item in group.items"
          :key="group.label + item.url"
          type="button"
          class="admin-search__item"
          data-testid="search-item"
          :class="{ 'admin-search__item--active': indexOfHit(group, item) === activeIndex }"
          @click="select(item)"
          @mousemove="activeIndex = indexOfHit(group, item)"
        >
          <span class="admin-search__item-title">{{ item.title }}</span>
          <span v-if="item.subtitle" class="admin-search__item-sub">{{ item.subtitle }}</span>
          <UidIcon
            v-if="indexOfHit(group, item) === activeIndex"
            :icon="CornerDownLeft"
            :size="13"
            class="admin-search__item-enter"
          />
        </button>
      </div>
    </div>
  </UidModal>
</template>

<style>
.admin-search__box {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: 4px 4px 12px;
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-search__box-icon { color: var(--uid-text-tertiary); flex: none; }
.admin-search__input {
  flex: 1;
  border: 0;
  outline: none;
  background: transparent;
  font-size: 16px;
  color: var(--uid-text-primary);
}
.admin-search__input::placeholder { color: var(--uid-text-tertiary); }
.admin-search__results {
  margin-top: 8px;
  max-height: 52vh;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.admin-search__hint {
  padding: 16px 8px;
  margin: 0;
  color: var(--uid-text-tertiary);
  font-size: 13px;
  text-align: center;
}
.admin-search__group { margin-bottom: 6px; }
.admin-search__group--recognized .admin-search__group-hd { color: var(--uid-color-primary, var(--uid-text-secondary)); }
.admin-search__group-hd {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--uid-text-tertiary);
}
.admin-search__item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--uid-radius-md);
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: var(--uid-text-primary);
}
.admin-search__item--active { background: var(--uid-color-surface-hover, var(--uid-border-subtle)); }
.admin-search__item-title { font-size: 14px; }
.admin-search__item-sub {
  font-size: 12px;
  color: var(--uid-text-tertiary);
}
.admin-search__item-enter { margin-left: auto; color: var(--uid-text-tertiary); flex: none; }
</style>

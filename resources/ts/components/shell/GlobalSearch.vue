<script setup lang="ts">
/**
 * GlobalSearch — командная палитра (⌘K). Два вида результатов:
 *   - «Разделы» — быстрый переход в любой раздел (из menu-store, фильтр
 *     по подстроке label, работает мгновенно, оффлайн).
 *   - записи ресурсов — серверный поиск GET /system/search?q= по всем
 *     searchable-полям (debounce), группировка по ресурсу, переход в карточку.
 *
 * Клавиатура: ↑/↓ — навигация, Enter — переход, Esc — закрыть (UidModal).
 * Открывается по ⌘K (AdminShell) или кликом по search-pill топбара.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { UidIcon, UidModal, UidSpinner } from '@dskripchenko/ui'
import { Search, CornerDownLeft } from 'lucide-vue-next'
import { getAdminClient } from '../../stores/registry'
import { useMenuStore, type MenuItem } from '../../stores/menu'
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
  label: string
  icon: string | null
  items: Hit[]
  hasMore?: boolean
  moreUrl?: string
}

const router = useRouter()
const menu = useMenuStore()

const query = ref<string>('')
const loading = ref<boolean>(false)
const serverGroups = ref<Group[]>([])
const activeIndex = ref<number>(0)
const inputRef = ref<HTMLInputElement | null>(null)

const open = computed<boolean>({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

/** Плоский список пунктов меню (рекурсивно) для nav-поиска. */
function flattenMenu(items: MenuItem[]): MenuItem[] {
  const out: MenuItem[] = []
  for (const it of items) {
    if (it.url) out.push(it)
    if (it.children?.length) out.push(...flattenMenu(it.children))
  }
  return out
}

const navGroup = computed<Group | null>(() => {
  const q = query.value.trim().toLowerCase()
  if (q.length === 0) return null
  const hits = flattenMenu(menu.visibleItems)
    .filter((it) => it.label.toLowerCase().includes(q))
    .slice(0, 6)
    .map<Hit>((it) => ({ title: it.label, subtitle: null, url: it.url as string }))
  if (hits.length === 0) return null
  return { label: 'Разделы', icon: null, items: hits }
})

/** Все группы для рендера: сначала навигация, затем записи. */
const groups = computed<Group[]>(() => {
  const g: Group[] = []
  if (navGroup.value) g.push(navGroup.value)
  g.push(...serverGroups.value)
  return g
})

/** Плоский список ссылок в порядке отображения — для стрелок/Enter. */
const flatHits = computed<Hit[]>(() => groups.value.flatMap((g) => g.items))

const hasQuery = computed<boolean>(() => query.value.trim().length >= 2)
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
    const res = await getAdminClient().get<{ groups: Group[] }>('/system/search', {
      params: { q },
    })
    if (mine !== seq) return // устаревший ответ — пришёл новый запрос
    serverGroups.value = Array.isArray(res.groups) ? res.groups : []
  } catch {
    if (mine === seq) serverGroups.value = []
  } finally {
    if (mine === seq) loading.value = false
  }
}

// Индекс пункта в плоском списке для подсветки active.
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
  void router.push(hit.url)
}

function onArrow(delta: number): void {
  const n = flatHits.value.length
  if (n === 0) return
  activeIndex.value = (activeIndex.value + delta + n) % n
}

// Автофокус + сброс при открытии.
watch(open, (isOpen) => {
  if (isOpen) {
    query.value = ''
    serverGroups.value = []
    activeIndex.value = 0
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
        class="admin-search__input"
        placeholder="Поиск по разделам и записям…"
        autocomplete="off"
        spellcheck="false"
        @keydown.down.prevent="onArrow(1)"
        @keydown.up.prevent="onArrow(-1)"
        @keydown.enter.prevent="select(flatHits[activeIndex])"
      />
      <UidSpinner v-if="loading" size="sm" />
    </div>

    <div class="admin-search__results">
      <p v-if="!hasQuery" class="admin-search__hint">
        Введите минимум 2 символа. Ищет по разделам и записям всех ресурсов.
      </p>
      <p v-else-if="showEmpty" class="admin-search__hint">
        Ничего не найдено по запросу «{{ query }}».
      </p>

      <div v-for="group in groups" :key="group.label" class="admin-search__group">
        <div class="admin-search__group-hd">
          <UidIcon v-if="resolveIcon(group.icon)" :icon="resolveIcon(group.icon)!" :size="12" />
          <span>{{ group.label }}</span>
        </div>
        <button
          v-for="item in group.items"
          :key="group.label + item.url"
          type="button"
          class="admin-search__item"
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

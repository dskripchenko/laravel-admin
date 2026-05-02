<script setup lang="ts">
/**
 * ResourceIndexPage — list-screen для одного Resource'а из manifest'а.
 * Архитектура по docs/design_handoff_laravel_admin/screens-shell.jsx (Resource List).
 *
 * Композиция:
 *   - Page header (title + count + actions cluster)
 *   - Filter bar с search + chips
 *   - Bulk toolbar (заменяет filter bar при selectedCount > 0)
 *   - UidTable с manifest.columns
 *   - States: loading (UidSkeleton ×8) / empty (UidEmptyState) / error (UidErrorState)
 *   - UidPagination внизу
 *
 * Host рендерит page через router. resource-slug приходит из props (либо
 * из route.params).
 */
import { computed, nextTick, onMounted, watch } from 'vue'
import {
  UidButton,
  UidEmptyState,
  UidErrorState,
  UidPagination,
  UidSkeleton,
  UidTable,
  type UidTableColumn,
} from '@dskripchenko/ui'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { useManifestStore } from '../../stores/manifest'
import { useNavigationStore } from '../../stores/navigation'
import { formatCell, type CellMeta } from './cellFormat'

interface Props {
  /** Slug ресурса (users/articles/etc). */
  slug: string
  /** Заголовок страницы. Если не задан — берётся из manifest'а. */
  title?: string | null
  /** Подпись под заголовком (например "Аналитика контента"). */
  subtitle?: string | null
  /** Текст primary-кнопки «Создать». */
  createLabel?: string
  /** Имя router-route для action «Создать». */
  createRouteName?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  subtitle: null,
  createLabel: 'Создать',
  createRouteName: null,
})

const emit = defineEmits<{
  /** Bulk-action triggered с текущим Set'ом id. */
  'bulk-action': [action: string, ids: Array<string | number>]
  /** Click на row — host решает (push edit / open view). */
  'row-click': [row: Record<string, unknown>]
}>()

const index = useResourceIndexStore()
const manifest = useManifestStore()
const nav = useNavigationStore()

const resourceMeta = computed(() => manifest.getResource(props.slug))

const displayTitle = computed(
  () => props.title ?? resourceMeta.value?.label ?? props.slug,
)

const columns = computed<UidTableColumn[]>(() => {
  const cols = resourceMeta.value?.columns ?? []
  return cols.map((c) => {
    const col = c as Record<string, unknown>
    return {
      key: String(col.key ?? col.name ?? ''),
      label: String(col.label ?? col.name ?? ''),
      sortable: Boolean(col.sortable),
      align: (col.align as 'left' | 'center' | 'right' | undefined) ?? 'left',
      width: typeof col.width === 'string' ? col.width : undefined,
    }
  }).filter((c) => c.key)
})

// Колоночная meta (preset / format / currency / etc.) из manifest'а.
// Используется для formatCell (datetime → 'd.m.Y H:i:s', money → '{val} {ccy}').
const columnMeta = computed<Record<string, { preset?: string; meta: CellMeta }>>(() => {
  const cols = resourceMeta.value?.columns ?? []
  const result: Record<string, { preset?: string; meta: CellMeta }> = {}
  for (const c of cols) {
    const col = c as Record<string, unknown>
    const key = String(col.key ?? col.name ?? '')
    if (!key) continue
    result[key] = {
      preset: typeof col.preset === 'string' ? col.preset : undefined,
      meta: (col.meta as CellMeta) ?? {},
    }
  }
  return result
})

function renderCell(key: string, slotProps: unknown): string {
  // UidTable scoped-slot передаёт {row: actualRow}.
  const row = (slotProps as { row?: Record<string, unknown> } | undefined)?.row
  const value = row?.[key]
  const m = columnMeta.value[key]
  return formatCell(value, m?.preset, m?.meta ?? {})
}

function rowFromSlot(slotProps: unknown): Record<string, unknown> | undefined {
  return (slotProps as { row?: Record<string, unknown> } | undefined)?.row
}

/**
 * Показывать ли filter-bar:
 *   - если есть данные → всегда (search + фильтры внутри);
 *   - если данных нет, но есть активный search/filter → ДА (чтобы пользователь
 *     мог сбросить и снова увидеть items);
 *   - если данных нет и фильтры/поиск пустые → НЕТ (initial empty state).
 */
const hasActiveFilters = computed<boolean>(
  () => index.search !== '' || Object.keys(index.filters).length > 0,
)
const showFilterBar = computed<boolean>(
  () => !index.isEmpty || hasActiveFilters.value,
)

const totalLabel = computed(() => {
  const t = index.meta.total
  if (t === 0) return ''
  return `${index.items.length} из ${t}`
})

/**
 * Обёртка над index.load — увеличивает navigation pending counter
 * (top loading-bar) на время запроса. router.beforeEach уже инкрементирует
 * counter при start navigation; этот wrap держит bar до конца data-fetch'а.
 */
async function loadWithProgress(): Promise<void> {
  nav.start()
  try {
    await index.load()
  } catch {
    // silent; ошибка отображается в hasError state
  } finally {
    nav.end()
  }
}

/**
 * Mount-init: setSlug + load data.
 *
 * При SPA navigation от router.push первичный data-fetch также делается
 * в router.beforeResolve (см. createAdminApp.ts) — он держит navigation
 * в pending пока данные не пришли. Здесь load всё равно вызываем как
 * resilient fallback (direct page mount, page reload, test).
 */
onMounted(async () => {
  index.setSlug(props.slug)
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  await loadWithProgress()
})

watch(
  () => props.slug,
  async (next, prev) => {
    if (next === prev) return
    index.setSlug(next)
    await loadWithProgress()
  },
)

async function onSortKeyUpdate(key: string | null): Promise<void> {
  // UidTable управляет своим 3-режимным cycle; здесь только применяем итог.
  // sortDirection приходит отдельным событием — apply через setSort одним
  // вызовом (после nextTick — Vue batches событий).
  await nextTick()
  await index.setSort(key, index.sortDirection)
}

async function onSortDirUpdate(dir: 'asc' | 'desc' | null): Promise<void> {
  await nextTick()
  await index.setSort(index.sortKey, dir)
}

function onSelectionUpdate(next: Set<string | number>): void {
  index.selection = next
}

async function onPageChange(page: number): Promise<void> {
  await index.setPage(page)
}

function onRowClick(row: Record<string, unknown>): void {
  emit('row-click', row)
}

function bulkAction(action: string): void {
  emit('bulk-action', action, [...index.selection])
}

async function retryLoad(): Promise<void> {
  await index.load().catch(() => undefined)
}
</script>

<template>
  <section class="admin-page admin-resource-index">
    <!-- Header -->
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ displayTitle }}</h1>
        <div v-if="subtitle || totalLabel" class="admin-page__count">
          <template v-if="subtitle">{{ subtitle }}</template>
          <template v-if="subtitle && totalLabel"> · </template>
          <template v-if="totalLabel">{{ totalLabel }}</template>
        </div>
      </div>
      <div class="admin-page__actions">
        <slot name="actions" />
        <UidButton
          v-if="createRouteName"
          variant="primary"
          @click="$router.push({ name: createRouteName })"
        >
          {{ createLabel }}
        </UidButton>
      </div>
    </header>

    <!-- Bulk toolbar (selection > 0) ИЛИ filter bar -->
    <div v-if="index.hasSelection" class="admin-bulk-toolbar" role="toolbar">
      <span class="admin-bulk-toolbar__count">
        Выбрано <b>{{ index.selectedCount }}</b>
      </span>
      <span class="admin-bulk-toolbar__divider" />
      <UidButton size="sm" variant="ghost" @click="bulkAction('publish')">Опубликовать</UidButton>
      <UidButton size="sm" variant="ghost" @click="bulkAction('archive')">Архивировать</UidButton>
      <UidButton size="sm" variant="ghost" @click="bulkAction('export')">Экспорт</UidButton>
      <UidButton size="sm" variant="danger" @click="bulkAction('delete')">Удалить</UidButton>
      <span class="admin-bulk-toolbar__spacer" />
      <UidButton size="sm" variant="ghost" @click="index.clearSelection">
        Снять выделение
      </UidButton>
    </div>

    <div v-else-if="showFilterBar" class="admin-filter-bar">
      <input
        :value="index.search"
        type="search"
        placeholder="Поиск…"
        class="admin-filter-bar__search"
        @keydown.enter="(e) => index.setSearch((e.target as HTMLInputElement).value)"
      />
      <slot name="filters" :filters="index.filters" :set-filter="index.setFilter" />
      <span class="admin-filter-bar__spacer" />
      <UidButton
        v-if="Object.keys(index.filters).length > 0"
        size="sm"
        variant="ghost"
        @click="index.clearFilters"
      >
        Сброс
      </UidButton>
    </div>

    <!-- States -->
    <!-- Initial-loading стейт (между setSlug и первым успехом load).
         Показываем placeholder вместо UidTable — иначе сама таблица
         рендерит "Нет данных" empty state, что создаёт flicker
         "Нет данных → реальные строки" при navigation. -->
    <div
      v-if="index.loading && index.items.length === 0"
      class="admin-resource-index__loading"
    >
      <UidSkeleton
        v-if="index.slowLoading"
        v-for="i in 8"
        :key="i"
        height="40px"
      />
    </div>
    <UidErrorState
      v-else-if="index.hasError"
      title="Не удалось загрузить данные"
      :description="index.error?.message ?? 'Попробуйте обновить страницу.'"
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton variant="primary" @click="retryLoad">Обновить</UidButton>
      </template>
    </UidErrorState>
    <UidEmptyState
      v-else-if="index.isEmpty"
      title="Пока пусто"
      description="Создайте первую запись или измените фильтры."
      class="admin-resource-index__state"
    >
      <template #actions>
        <UidButton
          v-if="createRouteName"
          variant="primary"
          @click="$router.push({ name: createRouteName })"
        >
          {{ createLabel }}
        </UidButton>
      </template>
    </UidEmptyState>

    <!-- Таблица — UidTable native selection (UidTable.selectable + selection prop). -->
    <div v-else class="admin-resource-index__table">
      <UidTable
        :columns="columns"
        :data="index.items"
        :sort-key="index.sortKey"
        :sort-direction="index.sortDirection"
        selectable
        :selection="index.selection"
        :row-key="(row) => index.rowId(row)"
        @update:sort-key="onSortKeyUpdate"
        @update:sort-direction="onSortDirUpdate"
        @update:selection="onSelectionUpdate"
        @row-click="onRowClick"
      >
        <template
          v-for="col in columns"
          #[col.key]="slotProps"
          :key="col.key"
        >
          <slot :name="`cell-${col.key}`" :row="rowFromSlot(slotProps)">
            {{ renderCell(col.key, slotProps) }}
          </slot>
        </template>
      </UidTable>
    </div>

    <!-- Pagination -->
    <footer
      v-if="!index.loading && !index.hasError && !index.isEmpty"
      class="admin-resource-index__footer"
    >
      <UidPagination
        :model-value="index.meta.page"
        :total="index.meta.total"
        :per-page="index.meta.per_page"
        @update:model-value="onPageChange"
      />
    </footer>
  </section>
</template>

<style>
.admin-resource-index__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
  margin-top: var(--uid-space-md);
  /* min-height предотвращает layout-collapse при первом mount'е,
     когда slowLoading ещё false (быстрый запрос — placeholder пустой). */
  min-height: 320px;
}
.admin-resource-index__state {
  margin-top: var(--uid-space-xl);
}
.admin-resource-index__table {
  /* table визуально приклеена к filter-bar / bulk-toolbar сверху —
     убираем gap, скругляем только нижние углы у table-wrap'а. */
  margin-top: 0;
}
.admin-resource-index__table .uid-table-wrap {
  border: 1px solid var(--uid-border-subtle);
  border-top: 0;
  border-radius: 0 0 var(--uid-radius-lg) var(--uid-radius-lg);
}
.admin-resource-index__footer {
  display: flex;
  justify-content: flex-end;
  padding: var(--uid-space-md) 0;
}

/* Filter bar */
.admin-filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-raised);
  border-radius: var(--uid-radius-lg) var(--uid-radius-lg) 0 0;
  border-bottom: 0;
  margin-top: var(--uid-space-md);
}
.admin-filter-bar__search {
  flex: 1;
  min-width: 200px;
  max-width: 320px;
  height: 28px;
  padding: 0 var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-primary);
  font-size: 13px;
  outline: none;
}
.admin-filter-bar__search:focus {
  border-color: var(--uid-accent);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
}
.admin-filter-bar__spacer { flex: 1; }

/* Bulk toolbar — dark zinc-900 surface, заменяет filter bar */
.admin-bulk-toolbar {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  background: var(--uid-color-zinc-900, #18181b);
  color: var(--uid-color-zinc-100, #f4f4f5);
  border-radius: var(--uid-radius-lg) var(--uid-radius-lg) 0 0;
  border: 1px solid var(--uid-color-zinc-900, #18181b);
  border-bottom: 0;
  margin-top: var(--uid-space-md);
}
.admin-bulk-toolbar__count { font-size: 13px; }
.admin-bulk-toolbar__count b { font-weight: var(--uid-font-weight-semibold); }
.admin-bulk-toolbar__divider {
  width: 1px;
  height: 20px;
  background: rgba(255, 255, 255, 0.15);
}
.admin-bulk-toolbar__spacer { flex: 1; }
.admin-bulk-toolbar .uid-button {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.15);
  color: var(--uid-color-zinc-100, #f4f4f5);
}
.admin-bulk-toolbar .uid-button:hover:not([disabled]) {
  background: rgba(255, 255, 255, 0.08);
}
.admin-bulk-toolbar .uid-button--danger {
  color: var(--uid-color-rose-400, #fb7185);
}

/* Selected row tint */
.admin-resource-index__row--selected {
  background: color-mix(in srgb, var(--uid-accent) 8%, transparent);
}
</style>

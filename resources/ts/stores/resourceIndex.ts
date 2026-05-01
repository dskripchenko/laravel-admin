/**
 * useResourceIndexStore — list-page state для одного Resource'а.
 *
 * Поддерживает:
 *   - items + pagination meta из envelope payload
 *   - filters (typed Record<string, unknown>) — отправляются как query
 *   - sort (key + direction)
 *   - selection (Set<row-id>) для bulk-actions; tri-state header-checkbox
 *   - loading/error состояния (UI рендерит UidSkeleton/UidErrorState)
 *
 * Один store-instance переиспользуется на разных Resource-страницах через
 * `useResourceIndexStore(slug)` с явным `setSlug` — это даёт чистый reset
 * между resource'ами.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'

export interface IndexMeta {
  page: number
  per_page: number
  total: number
  last_page: number
}

export interface IndexParams {
  page?: number
  per_page?: number
  sort?: string | null
  direction?: 'asc' | 'desc' | null
  search?: string | null
  filters?: Record<string, unknown>
}

interface ListResponse {
  data: Array<Record<string, unknown>>
  meta: IndexMeta
}

const DEFAULT_META: IndexMeta = { page: 1, per_page: 20, total: 0, last_page: 1 }

export const useResourceIndexStore = defineStore('admin-resource-index', () => {
  /** Текущий resource-slug (urls/users/posts/etc). */
  const slug = ref<string | null>(null)

  const items = ref<Array<Record<string, unknown>>>([])
  const meta = ref<IndexMeta>({ ...DEFAULT_META })

  const loading = ref(false)
  const error = ref<Error | null>(null)

  const search = ref<string>('')
  const filters = ref<Record<string, unknown>>({})
  const sortKey = ref<string | null>(null)
  const sortDirection = ref<'asc' | 'desc'>('asc')

  /** Set ID-шников выбранных строк (bulk-actions). */
  const selection = ref<Set<string | number>>(new Set())

  const isEmpty = computed(() => !loading.value && error.value === null && items.value.length === 0)
  const selectedCount = computed(() => selection.value.size)
  const hasSelection = computed(() => selection.value.size > 0)
  const hasError = computed(() => error.value !== null)

  /** Tri-state: 'all' / 'mixed' / 'none' для header-checkbox. */
  const selectionState = computed<'all' | 'mixed' | 'none'>(() => {
    if (selection.value.size === 0) return 'none'
    if (items.value.length === 0) return 'none'
    const allSelected = items.value.every((row) => selection.value.has(rowId(row)))
    return allSelected ? 'all' : 'mixed'
  })

  /** Извлекает ID строки. По умолчанию — поле `id`; host может переопределить. */
  function rowId(row: Record<string, unknown>): string | number {
    return (row.id ?? row.key ?? '') as string | number
  }

  /** Сменить ресурс — сброс state'а. */
  function setSlug(next: string | null): void {
    if (slug.value === next) return
    slug.value = next
    reset()
  }

  function reset(): void {
    items.value = []
    meta.value = { ...DEFAULT_META }
    loading.value = false
    error.value = null
    search.value = ''
    filters.value = {}
    sortKey.value = null
    sortDirection.value = 'asc'
    selection.value = new Set()
  }

  function buildParams(override: IndexParams = {}): Record<string, unknown> {
    const params: Record<string, unknown> = {
      page: override.page ?? meta.value.page,
      per_page: override.per_page ?? meta.value.per_page,
    }
    const ss = override.search ?? search.value
    if (ss) params.search = ss

    const sk = override.sort ?? sortKey.value
    if (sk) {
      params.sort = sk
      params.direction = override.direction ?? sortDirection.value
    }

    const f = override.filters ?? filters.value
    for (const [k, v] of Object.entries(f)) {
      if (v === null || v === undefined || v === '') continue
      params[`filter[${k}]`] = Array.isArray(v) ? v.join(',') : String(v)
    }

    return params
  }

  /** Загрузить страницу. Без аргументов — текущие фильтры/сортировка/page. */
  async function load(override: IndexParams = {}): Promise<void> {
    if (!slug.value) {
      throw new Error('useResourceIndexStore.load() called before setSlug()')
    }
    loading.value = true
    error.value = null
    try {
      const client = getAdminClient()
      const params = buildParams(override)
      const res = await client.get<ListResponse>(`/resources/${slug.value}/list`, { params })
      items.value = res.data
      meta.value = res.meta
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  async function setSearch(value: string): Promise<void> {
    search.value = value
    meta.value.page = 1
    await load()
  }

  async function setFilter(key: string, value: unknown): Promise<void> {
    if (value === null || value === undefined || value === '') {
      delete filters.value[key]
    } else {
      filters.value[key] = value
    }
    meta.value.page = 1
    await load()
  }

  async function clearFilters(): Promise<void> {
    filters.value = {}
    meta.value.page = 1
    await load()
  }

  async function setSort(key: string, direction: 'asc' | 'desc' = 'asc'): Promise<void> {
    sortKey.value = key
    sortDirection.value = direction
    await load()
  }

  async function toggleSort(key: string): Promise<void> {
    if (sortKey.value === key) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortKey.value = key
      sortDirection.value = 'asc'
    }
    await load()
  }

  async function setPage(page: number): Promise<void> {
    meta.value.page = page
    await load()
  }

  function toggleRow(id: string | number): void {
    const next = new Set(selection.value)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    selection.value = next
  }

  /** Toggle: выделить все на странице если что-то не выбрано, иначе очистить. */
  function toggleAllOnPage(): void {
    if (selectionState.value === 'all') {
      selection.value = new Set()
      return
    }
    const next = new Set(selection.value)
    for (const row of items.value) next.add(rowId(row))
    selection.value = next
  }

  function clearSelection(): void {
    selection.value = new Set()
  }

  /** Проверить выбор конкретной строки. */
  function isSelected(id: string | number): boolean {
    return selection.value.has(id)
  }

  return {
    // state
    slug,
    items,
    meta,
    loading,
    error,
    search,
    filters,
    sortKey,
    sortDirection,
    selection,
    // getters
    isEmpty,
    hasError,
    hasSelection,
    selectedCount,
    selectionState,
    // actions
    setSlug,
    reset,
    load,
    setSearch,
    setFilter,
    clearFilters,
    setSort,
    toggleSort,
    setPage,
    toggleRow,
    toggleAllOnPage,
    clearSelection,
    isSelected,
    rowId,
  }
})

/**
 * useResourceIndexStore — the list page's state for a single resource.
 *
 * It carries:
 *   - the items and the pagination meta from the envelope payload
 *   - the filters (a typed Record<string, unknown>), sent as the query
 *   - the sort (key and direction)
 *   - the selection (a Set of row ids) for bulk actions, with a tri-state
 *     header checkbox
 *   - the loading and error states, which the UI renders as UidSkeleton and
 *     UidErrorState
 *
 * One store instance is reused across the resource pages through
 * `useResourceIndexStore(slug)` with an explicit `setSlug`, which gives a
 * clean reset between resources.
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

export type SortDirection = 'asc' | 'desc' | null

export interface IndexParams {
  page?: number
  per_page?: number
  sort?: string | null
  direction?: SortDirection
  search?: string | null
  filters?: Record<string, unknown>
}

interface ListResponse {
  data: Array<Record<string, unknown>>
  meta: IndexMeta
}

const DEFAULT_META: IndexMeta = { page: 1, per_page: 20, total: 0, last_page: 1 }

export const useResourceIndexStore = defineStore('admin-resource-index', () => {
  /** The current resource slug: urls, users, posts and so on. */
  const slug = ref<string | null>(null)

  const items = ref<Array<Record<string, unknown>>>([])
  const meta = ref<IndexMeta>({ ...DEFAULT_META })

  const loading = ref(false)
  /**
   * `slowLoading` turns true only when the loading lasts longer than
   * SLOW_LOADING_DELAY ms. The UI draws its skeleton off this, so that fast
   * responses (under 200ms) do not flicker 'data → skeleton → data'. When the
   * request is quick, the skeleton never appears.
   */
  const slowLoading = ref(false)
  const error = ref<Error | null>(null)
  let slowLoadingTimer: ReturnType<typeof setTimeout> | null = null
  const SLOW_LOADING_DELAY = 200

  const search = ref<string>('')
  const filters = ref<Record<string, unknown>>({})
  const sortKey = ref<string | null>(null)
  const sortDirection = ref<SortDirection>(null)

  /** The set of selected row ids, for the bulk actions. */
  const selection = ref<Set<string | number>>(new Set())

  const isEmpty = computed(() => !loading.value && error.value === null && items.value.length === 0)
  const selectedCount = computed(() => selection.value.size)
  const hasSelection = computed(() => selection.value.size > 0)
  const hasError = computed(() => error.value !== null)

  /** Tri-state for the header checkbox: 'all' / 'mixed' / 'none'. */
  const selectionState = computed<'all' | 'mixed' | 'none'>(() => {
    if (selection.value.size === 0) return 'none'
    if (items.value.length === 0) return 'none'
    const allSelected = items.value.every((row) => selection.value.has(rowId(row)))
    return allSelected ? 'all' : 'mixed'
  })

  /** Extracts a row's id. The `id` field by default; the host may override it. */
  function rowId(row: Record<string, unknown>): string | number {
    return (row.id ?? row.key ?? '') as string | number
  }

  /**
   * Switches to another resource, resetting the state.
   *
   * `loading=true` is set right away so that the window between setSlug() and
   * load() does not render the empty state ("No data") — that flickered while
   * navigating between resources.
   */
  function setSlug(next: string | null): void {
    if (slug.value === next) return
    slug.value = next
    reset()
    loading.value = true
  }

  function reset(): void {
    items.value = []
    meta.value = { ...DEFAULT_META }
    loading.value = false
    error.value = null
    search.value = ''
    filters.value = {}
    sortKey.value = null
    sortDirection.value = null
    selection.value = new Set()
  }

  function buildParams(override: IndexParams = {}): Record<string, unknown> {
    const params: Record<string, unknown> = {
      page: override.page ?? meta.value.page,
      per_page: override.per_page ?? meta.value.per_page,
    }
    // Free text: the backend expects `q`. See HttpFilterParser::searchTerm.
    const ss = override.search ?? search.value
    if (ss) params.q = ss

    // Order is an array of {column, direction}; the backend reads `order[]`
    // through input('order'). With direction === null we add no order at all —
    // that is the "off" step of the three-way sort.
    const sk = override.sort ?? sortKey.value
    const dir = override.direction ?? sortDirection.value
    if (sk && dir !== null) {
      params.order = [{ column: sk, direction: dir }]
    }

    // Filters go map-style, {column: value} — mode 1 of HttpFilterParser's auto-detection.
    const f = override.filters ?? filters.value
    const filtersMap: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(f)) {
      if (v === null || v === undefined || v === '') continue
      filtersMap[k] = Array.isArray(v) ? v.join(',') : v
    }
    if (Object.keys(filtersMap).length > 0) params.filters = filtersMap

    return params
  }

  /** Loads a page. Without arguments it uses the current filters, sort and page. */
  async function load(override: IndexParams = {}): Promise<void> {
    if (!slug.value) {
      throw new Error('useResourceIndexStore.load() called before setSlug()')
    }
    loading.value = true
    error.value = null
    // The debounced flag behind the skeleton: true only when the request takes over 200ms.
    if (slowLoadingTimer !== null) clearTimeout(slowLoadingTimer)
    slowLoadingTimer = setTimeout(() => {
      slowLoading.value = true
    }, SLOW_LOADING_DELAY)
    try {
      const client = getAdminClient()
      const body = buildParams(override)
      // The backend's ResourceController.search: POST /{slug}/search. The
      // body carries the parameters — filters, sort, pagination — and the
      // answer is {data, meta:{page,per_page,total,last_page,...}}.
      const res = await client.post<ListResponse>(`/${slug.value}/search`, body)
      items.value = res.data
      meta.value = res.meta
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
      slowLoading.value = false
      if (slowLoadingTimer !== null) {
        clearTimeout(slowLoadingTimer)
        slowLoadingTimer = null
      }
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

  async function setSort(key: string | null, direction: SortDirection = 'asc'): Promise<void> {
    sortKey.value = key
    sortDirection.value = direction
    await load()
  }

  /**
   * A three-way sort. Clicking the same key walks asc → desc → off
   * (sort=null); clicking another key starts at asc.
   */
  async function toggleSort(key: string): Promise<void> {
    if (sortKey.value === key) {
      if (sortDirection.value === 'asc') {
        sortDirection.value = 'desc'
      } else if (sortDirection.value === 'desc') {
        sortKey.value = null
        sortDirection.value = null
      } else {
        sortDirection.value = 'asc'
      }
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

  /** A toggle: select everything on the page unless it is all selected already, otherwise clear. */
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

  /** Tells whether a particular row is selected. */
  function isSelected(id: string | number): boolean {
    return selection.value.has(id)
  }

  return {
    // state
    slug,
    items,
    meta,
    loading,
    slowLoading,
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

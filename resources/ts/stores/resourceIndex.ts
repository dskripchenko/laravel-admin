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
 *
 * A list embedded into a screen (Layout::resourceIndex()) gets an instance of
 * its own through `resourceIndexStoreFor(key)`, so several lists on one
 * screen — side by side or in tabs — keep their search, filters, page, sort
 * and selection apart. The page provides its instance to the kanban and
 * calendar views below it (`provideResourceIndexStore` /
 * `injectResourceIndexStore`).
 */

import { defineStore } from 'pinia'
import { computed, inject, provide, ref, type InjectionKey } from 'vue'
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

function resourceIndexSetup() {
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

  /**
   * The view the index page shows: null for the resource's base view (the
   * list, or the tree of a hierarchical resource), or 'kanban' / 'calendar'.
   * It travels in the URL as `?view=` and in saved views.
   */
  const view = ref<string | null>(null)

  /**
   * Whether the table is the view on screen. The kanban and the calendar load
   * their own data, so a search or filter change does not fetch a list page
   * nobody sees.
   */
  const listActive = computed(() => view.value === null || view.value === 'list' || view.value === 'tree')

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
    view.value = null
    selection.value = new Set()
  }

  /**
   * Back to the plain list — no search, filters, sort, view, first page —
   * keeping the rows and the selection. A list on a screen starts from this
   * before reading its URL keys, so the address alone says what it shows.
   */
  function resetQueryState(): void {
    search.value = ''
    filters.value = {}
    sortKey.value = null
    sortDirection.value = null
    view.value = null
    meta.value = { ...meta.value, page: 1 }
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

  /**
   * What the list is narrowed and ordered by — `q`, `filters`, `order` — in
   * the shape search takes, for the requests that must see the same rows: the
   * export.
   */
  function queryParams(): Record<string, unknown> {
    const { page: _page, per_page: _perPage, ...rest } = buildParams()
    return rest
  }

  /**
   * The list's state as a URL query — `q`, `sort` (`-column` for descending),
   * `page`, `filter[name]`, `view` — so a filtered list can be linked, bookmarked and
   * kept across a reload. Arrays become comma lists, as the request sends them.
   *
   * `prefix` namespaces the keys of one list among several on a screen
   * (`orders.q`, `orders.filter[status]`); empty, they are the plain ones.
   */
  function toQuery(prefix: string = ''): Record<string, string> {
    const query: Record<string, string> = {}
    if (search.value) query[`${prefix}q`] = search.value
    if (sortKey.value && sortDirection.value !== null) {
      query[`${prefix}sort`] = (sortDirection.value === 'desc' ? '-' : '') + sortKey.value
    }
    if (meta.value.page > 1) query[`${prefix}page`] = String(meta.value.page)
    if (view.value !== null) query[`${prefix}view`] = view.value
    for (const [k, v] of Object.entries(filters.value)) {
      if (v === null || v === undefined || v === '') continue
      query[`${prefix}filter[${k}]`] = Array.isArray(v) ? v.join(',') : String(v)
    }
    return query
  }

  /**
   * Takes the state from a URL query written by toQuery() with the same
   * prefix; what it does not know is ignored. Returns whether anything was set.
   */
  function fromQuery(query: Record<string, unknown>, prefix: string = ''): boolean {
    const first = (v: unknown): string | null => {
      const x = Array.isArray(v) ? v[0] : v
      return typeof x === 'string' && x !== '' ? x : null
    }
    let touched = false
    const q = first(query[`${prefix}q`])
    if (q !== null) {
      search.value = q
      touched = true
    }
    const sort = first(query[`${prefix}sort`])
    if (sort !== null) {
      const desc = sort.startsWith('-')
      sortKey.value = desc ? sort.slice(1) : sort
      sortDirection.value = desc ? 'desc' : 'asc'
      touched = true
    }
    const v = first(query[`${prefix}view`])
    if (v !== null) {
      view.value = v
      touched = true
    }
    const page = Number(first(query[`${prefix}page`]))
    if (Number.isInteger(page) && page > 1) {
      meta.value = { ...meta.value, page }
      touched = true
    }
    const next: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(query)) {
      if (!key.startsWith(`${prefix}filter[`)) continue
      const m = /^filter\[(.+)\]$/.exec(key.slice(prefix.length))
      const v = first(value)
      if (m && v !== null) next[m[1]] = v
    }
    if (Object.keys(next).length > 0) {
      filters.value = next
      touched = true
    }
    return touched
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
    if (listActive.value) await load()
  }

  async function setFilter(key: string, value: unknown): Promise<void> {
    if (value === null || value === undefined || value === '') {
      delete filters.value[key]
    } else {
      filters.value[key] = value
    }
    meta.value.page = 1
    if (listActive.value) await load()
  }

  async function clearFilters(): Promise<void> {
    filters.value = {}
    meta.value.page = 1
    if (listActive.value) await load()
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
    view,
    // getters
    listActive,
    isEmpty,
    hasError,
    hasSelection,
    selectedCount,
    selectionState,
    // actions
    setSlug,
    reset,
    resetQueryState,
    load,
    queryParams,
    toQuery,
    fromQuery,
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
}

export const useResourceIndexStore = defineStore('admin-resource-index', resourceIndexSetup)

export type ResourceIndexStore = ReturnType<typeof useResourceIndexStore>

const keyedStores = new Map<string, () => ResourceIndexStore>()

/**
 * The index state of one list among several: an instance per `key`, apart
 * from the resource pages' shared one. A null key is that shared one.
 */
export function resourceIndexStoreFor(key: string | null | undefined): ResourceIndexStore {
  if (key === null || key === undefined || key === '') return useResourceIndexStore()
  let use = keyedStores.get(key)
  if (!use) {
    use = defineStore(`admin-resource-index:${key}`, resourceIndexSetup) as unknown as () => ResourceIndexStore
    keyedStores.set(key, use)
  }
  return use()
}

const RESOURCE_INDEX_STORE: InjectionKey<ResourceIndexStore> = Symbol('admin-resource-index-store')

/** Hands the page's index state down to its kanban and calendar views. */
export function provideResourceIndexStore(store: ResourceIndexStore): void {
  provide(RESOURCE_INDEX_STORE, store)
}

/** The index state of the page above; the shared one outside a page. */
export function injectResourceIndexStore(): ResourceIndexStore {
  return inject(RESOURCE_INDEX_STORE, null) ?? useResourceIndexStore()
}


import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useResourceIndexStore } from './resourceIndex'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'

describe('useResourceIndexStore', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('throws if load() called before setSlug()', async () => {
    const s = useResourceIndexStore()
    await expect(s.load()).rejects.toThrow(/before setSlug/)
  })

  it('load fetches list + meta', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: {
        data: [{ id: 1, title: 'A' }, { id: 2, title: 'B' }],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1 },
      },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    expect(s.items).toHaveLength(2)
    expect(s.meta.total).toBe(2)
    expect(s.isEmpty).toBe(false)
    expect(s.hasError).toBe(false)
  })

  it('isEmpty after load returns no items', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    expect(s.isEmpty).toBe(true)
  })

  it('captures error on network failure', async () => {
    mock.onPost('/articles/search').networkError()
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await expect(s.load()).rejects.toThrow()
    expect(s.hasError).toBe(true)
    expect(s.error).not.toBeNull()
  })

  it('setSlug resets state when changing resources', async () => {
    mock.onPost('/a/search').reply(200, {
      success: true, payload: { data: [{ id: 1 }], meta: { page: 1, per_page: 20, total: 1, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('a')
    await s.load()
    expect(s.items).toHaveLength(1)

    s.setSlug('b')
    expect(s.items).toEqual([])
    expect(s.meta.total).toBe(0)
  })

  it('setSearch resets to page 1 + reloads with q in body', async () => {
    let capturedBody: Record<string, unknown> | null = null
    mock.onPost('/articles/search').reply((config) => {
      capturedBody = JSON.parse(config.data ?? '{}')
      return [200, {
        success: true,
        payload: {
          data: [{ id: 1 }],
          meta: { page: 1, per_page: 20, total: 1, last_page: 1 },
        },
      }]
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    s.meta.page = 5
    await s.setSearch('hello')
    expect(s.search).toBe('hello')
    expect(s.meta.page).toBe(1)
    expect(capturedBody).toMatchObject({ q: 'hello', page: 1 })
  })

  it('setFilter adds + clears + reloads', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.setFilter('status', 'published')
    expect(s.filters.status).toBe('published')

    await s.setFilter('status', null)
    expect(s.filters.status).toBeUndefined()
  })

  it('toggleSort 3-режимный: asc → desc → off (null) → asc', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')

    await s.toggleSort('title')
    expect(s.sortKey).toBe('title')
    expect(s.sortDirection).toBe('asc')

    await s.toggleSort('title')
    expect(s.sortDirection).toBe('desc')

    // Третий режим: off — sortKey становится null, direction null.
    await s.toggleSort('title')
    expect(s.sortKey).toBeNull()
    expect(s.sortDirection).toBeNull()

    // Четвёртый клик по тому же столбцу = новый sort, asc.
    await s.toggleSort('title')
    expect(s.sortKey).toBe('title')
    expect(s.sortDirection).toBe('asc')
  })

  it('toggleSort on different key resets to asc', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.toggleSort('title')
    await s.toggleSort('title') // desc
    await s.toggleSort('author') // → asc
    expect(s.sortKey).toBe('author')
    expect(s.sortDirection).toBe('asc')
  })

  it('selection: toggleRow + selectionState', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true, payload: { data: [{ id: 1 }, { id: 2 }, { id: 3 }], meta: { page: 1, per_page: 20, total: 3, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    expect(s.selectionState).toBe('none')
    s.toggleRow(1)
    expect(s.selectionState).toBe('mixed')
    expect(s.selectedCount).toBe(1)
    expect(s.isSelected(1)).toBe(true)
    s.toggleRow(1)
    expect(s.selectionState).toBe('none')
  })

  it('toggleAllOnPage selects all then clears', async () => {
    mock.onPost('/articles/search').reply(200, {
      success: true, payload: { data: [{ id: 1 }, { id: 2 }], meta: { page: 1, per_page: 20, total: 2, last_page: 1 } },
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.load()
    s.toggleAllOnPage()
    expect(s.selectionState).toBe('all')
    expect(s.selectedCount).toBe(2)
    s.toggleAllOnPage()
    expect(s.selectionState).toBe('none')
  })

  it('clearSelection wipes all', async () => {
    const s = useResourceIndexStore()
    s.selection = new Set([1, 2, 3])
    s.clearSelection()
    expect(s.selectedCount).toBe(0)
  })

  it('passes filters as map under `filters` body key', async () => {
    let capturedBody: Record<string, unknown> | null = null
    mock.onPost('/articles/search').reply((config) => {
      capturedBody = JSON.parse(config.data ?? '{}')
      return [200, {
        success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
      }]
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.setFilter('status', 'published')
    expect(capturedBody).toMatchObject({
      filters: { status: 'published' },
    })
  })

  it('array filter values joined by comma', async () => {
    let capturedBody: Record<string, unknown> | null = null
    mock.onPost('/articles/search').reply((config) => {
      capturedBody = JSON.parse(config.data ?? '{}')
      return [200, {
        success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
      }]
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.setFilter('cat', ['Backend', 'Frontend'])
    expect(capturedBody).toMatchObject({
      filters: { cat: 'Backend,Frontend' },
    })
  })

  it('passes order array with column+direction to backend', async () => {
    let capturedBody: Record<string, unknown> | null = null
    mock.onPost('/articles/search').reply((config) => {
      capturedBody = JSON.parse(config.data ?? '{}')
      return [200, {
        success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
      }]
    })
    const s = useResourceIndexStore()
    s.setSlug('articles')
    await s.toggleSort('title')
    expect(capturedBody).toMatchObject({
      order: [{ column: 'title', direction: 'asc' }],
    })
  })

  it('rowId fallback uses key when no id', () => {
    const s = useResourceIndexStore()
    expect(s.rowId({ key: 'k1' })).toBe('k1')
    expect(s.rowId({ id: 42 })).toBe(42)
  })
})

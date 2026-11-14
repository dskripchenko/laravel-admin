/**
 * rowOpen 'drawer': a row opens in a panel over the list, not on its page;
 * the record is viewed and edited there, a save or an action reloads the
 * list, and ‹ › / Alt+↑ ↓ walk to the neighbouring records — across pages,
 * and into the place of a record that has left a filtered queue.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ResourceIndexPage from './ResourceIndexPage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { clearInfolistRegistry } from '../infolist/registry'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/r/reviews', name: 'admin.resource.reviews.index', component: Stub },
      { path: '/r/reviews/:id', name: 'admin.resource.reviews.view', component: Stub },
      { path: '/r/reviews/:id/edit', name: 'admin.resource.reviews.edit', component: Stub },
    ],
  })

function seedManifest(features: Record<string, unknown> = {}): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [
      {
        slug: 'reviews',
        label: 'Отзывы',
        permissions: { view: 'admin.reviews.view' },
        fields: [{ kind: 'field', type: 'input', name: 'title', label: 'Заголовок' }],
        infolist: [{ type: 'text', name: 'title', label: 'Заголовок' }],
        columns: [
          { type: 'text', key: 'id', label: 'ID' },
          { type: 'text', key: 'title', label: 'Заголовок' },
        ],
        filters: [],
        actions: [
          { name: 'approve', label: 'Одобрить', type: 'button', position: ['row', 'bulk'], attributes: { requiresSelection: true } },
          { name: 'note', label: 'Заметка', type: 'button', position: ['row'], attributes: { keepsRecord: true } },
        ],
        searchable: ['title'],
        with: [],
        features,
      },
    ],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
}

const rows = (ids: number[]) => ids.map((id) => ({ id, title: `Review ${id}` }))

const page = (ids: number[], p = 1, lastPage = 1) => ({
  success: true,
  payload: { data: rows(ids), meta: { page: p, per_page: 3, total: lastPage * 3, last_page: lastPage } },
})

const read = (id: number | string) => ({ success: true, payload: { record: { id: Number(id), title: `Review ${id}` }, title: `Review ${id}` } })

const drawer = (): Element | null => document.querySelector('.admin-record-drawer')
const byTestId = (id: string): HTMLElement | null => document.querySelector(`[data-testid="${id}"]`)

describe('ResourceIndexPage — rows open in a drawer', () => {
  let mock: MockAdapter
  let router: Router
  let searches: Array<Record<string, unknown>>
  let reads: string[]
  let w: VueWrapper | null = null

  beforeEach(async () => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    searches = []
    reads = []
    mock.onPost('/reviews/search').reply((config) => {
      const body = JSON.parse(String(config.data ?? '{}')) as Record<string, unknown>
      searches.push(body)
      return [200, body.page === 2 ? page([4, 5], 2, 2) : page([1, 2, 3], 1, 2)]
    })
    mock.onGet('/reviews/read').reply((config) => {
      const id = String((config.params as { id: unknown }).id)
      reads.push(id)
      return [200, read(id)]
    })
    seedManifest()
    clearRegistry()
    registerBuiltinComponents()
    clearInfolistRegistry()
    registerBuiltinInfolistEntries()
    router = mkRouter()
    await router.push('/r/reviews')
    await router.isReady()
  })

  afterEach(() => {
    w?.unmount()
    w = null
    mock.reset()
    clearAdminClient()
    clearRegistry()
    clearInfolistRegistry()
    document.body.innerHTML = ''
  })

  const mountPage = (props: Record<string, unknown> = {}) => {
    w = mount(ResourceIndexPage, {
      props: { slug: 'reviews', rowOpen: 'drawer', ...props },
      global: { plugins: [router] },
      attachTo: document.body,
    })
    return w
  }

  const clickRow = async (n: number) => {
    const cells = w!.findAll('tbody tr')[n]!.findAll('td')
    await cells[cells.length - 2]!.trigger('click')
    await flushPromises()
  }

  it('a row click opens the record in the drawer and stays on the list', async () => {
    mountPage()
    await flushPromises()
    await clickRow(1)
    expect(router.currentRoute.value.name).toBe('admin.resource.reviews.index')
    expect(router.currentRoute.value.query.record).toBe('2')
    expect(reads).toEqual(['2'])
    expect(drawer()).not.toBeNull()
    expect(byTestId('record-drawer-view')?.textContent).toContain('Review 2')
  })

  it('the resource itself may ask for the drawer (Resource::rowOpen())', async () => {
    seedManifest({ rowOpen: 'drawer' })
    mountPage({ rowOpen: null })
    await flushPromises()
    await clickRow(0)
    expect(router.currentRoute.value.name).toBe('admin.resource.reviews.index')
    expect(reads).toEqual(['1'])
  })

  it('next and previous walk the rows, across the pages, by button and by Alt+↓ / Alt+↑', async () => {
    mountPage()
    await flushPromises()
    await clickRow(1)
    byTestId('record-drawer-next')!.click()
    await flushPromises()
    expect(reads.at(-1)).toBe('3')

    // The last row of the page: the next page is loaded and its first row opened.
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true }))
    await flushPromises()
    expect(searches.at(-1)?.page).toBe(2)
    expect(reads.at(-1)).toBe('4')
    expect(router.currentRoute.value.query.record).toBe('4')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', altKey: true }))
    await flushPromises()
    expect(searches.at(-1)?.page).toBe(1)
    expect(reads.at(-1)).toBe('3')
  })

  it('a save in the drawer goes to the server, reloads the list and returns to the view', async () => {
    let saved: Record<string, unknown> | null = null
    mock.onPost('/reviews/update').reply((config) => {
      saved = JSON.parse(String(config.data))
      return [200, { success: true, payload: { record: { id: 1, title: 'Changed' } } }]
    })
    mountPage()
    await flushPromises()
    await clickRow(0)
    byTestId('record-drawer-edit')!.click()
    await flushPromises()
    const input = document.querySelector('[data-testid="record-drawer-form"] input') as HTMLInputElement
    expect(input).not.toBeNull()
    input.value = 'Changed'
    input.dispatchEvent(new Event('input'))
    await flushPromises()
    const before = searches.length
    byTestId('record-drawer-save')!.click()
    await flushPromises()
    expect(saved).toMatchObject({ id: 1, title: 'Changed' })
    expect(searches.length).toBe(before + 1)
    expect(byTestId('record-drawer-view')).not.toBeNull()
  })

  it('with nextAfterAction an action moves on to the record that took the place of the one handled', async () => {
    let handled: unknown = null
    mock.onPost('/reviews/action').reply((config) => {
      handled = JSON.parse(String(config.data))
      // The approved review leaves the queue.
      mock.onPost('/reviews/search').reply(() => [200, page([1, 3], 1, 1)])
      return [200, { success: true, payload: { affected: 1 } }]
    })
    mountPage({ nextAfterAction: true })
    await flushPromises()
    await clickRow(1)
    byTestId('action-approve')!.click()
    await flushPromises()
    expect(handled).toMatchObject({ key: 'approve', ids: [2] })
    expect(reads.at(-1)).toBe('3')
    expect(router.currentRoute.value.query.record).toBe('3')
  })

  it('with nextAfterAction an action that keeps its record (keepsRecord) leaves it open', async () => {
    mock.onPost('/reviews/action').reply(() => [200, { success: true, payload: { affected: 1 } }])
    mountPage({ nextAfterAction: true })
    await flushPromises()
    await clickRow(1)
    const before = searches.length
    byTestId('action-note')!.click()
    await flushPromises()
    // The list still reloads, the record stays.
    expect(searches.length).toBe(before + 1)
    expect(reads.at(-1)).toBe('2')
    expect(router.currentRoute.value.query.record).toBe('2')
  })

  it('keep_record in the answer decides over the action\'s own flag, both ways', async () => {
    let keep = true
    mock.onPost('/reviews/action').reply(() => [200, { success: true, payload: { affected: 1, keep_record: keep } }])
    mountPage({ nextAfterAction: true })
    await flushPromises()
    await clickRow(0)
    // approve moves on by default; its answer keeps the record.
    byTestId('action-approve')!.click()
    await flushPromises()
    expect(router.currentRoute.value.query.record).toBe('1')

    // note keeps the record by default; its answer moves on.
    keep = false
    byTestId('action-note')!.click()
    await flushPromises()
    expect(router.currentRoute.value.query.record).toBe('2')
  })

  it('a link with the record opens the drawer; closing it drops the key', async () => {
    await router.push('/r/reviews?record=3')
    mountPage()
    await flushPromises()
    expect(reads).toEqual(['3'])
    expect(drawer()).not.toBeNull()
    ;(document.querySelector('.uid-drawer__close') as HTMLElement).click()
    await flushPromises()
    expect(drawer()).toBeNull()
    expect(router.currentRoute.value.query.record).toBeUndefined()
  })

  it('a named list keeps the record under its own key', async () => {
    mountPage({ stateKey: 'screen:reviews', queryKey: 'reviews' })
    await flushPromises()
    await clickRow(0)
    expect(router.currentRoute.value.query['reviews.record']).toBe('1')
    expect(router.currentRoute.value.query.record).toBeUndefined()
  })
})

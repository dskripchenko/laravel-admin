/**
 * Several Layout::resourceIndex() on one screen: each list keeps its own
 * search, filters, page and selection, and its own keys in the address; a
 * list in a closed tab loads nothing until the tab is opened; the open tab
 * lives in the address (Tabs::persistInQuery()) and can be linked.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import TabsLayout from './TabsLayout.vue'
import ResourceIndexLayout from './ResourceIndexLayout.vue'
import { clearRegistry, registerLayout } from '../render/registry'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useManifestStore } from '../../stores/manifest'
import { resourceIndexStoreFor, useResourceIndexStore } from '../../stores/resourceIndex'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/screens/product', name: 'admin.screen.product', component: Stub },
      { path: '/r/contracts/:id', name: 'admin.resource.contracts.view', component: Stub },
      { path: '/r/quotes/:id', name: 'admin.resource.quotes.view', component: Stub },
    ],
  })

const resource = (slug: string, label: string) => ({
  slug,
  label,
  permissions: { view: `admin.${slug}.view` },
  fields: [],
  columns: [
    { type: 'text', key: 'id', label: 'ID' },
    { type: 'text', key: 'title', label: 'Title' },
  ],
  filters: [],
  actions: [],
  searchable: ['title'],
  with: [],
  features: {},
})

function seedManifest(): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [resource('contracts', 'Договоры'), resource('quotes', 'Котировки')],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  } as never
}

const page = (data: Array<Record<string, unknown>>) => ({
  success: true,
  payload: { data, meta: { page: 1, per_page: 20, total: data.length, last_page: 1 } },
})

const listNode = (slug: string, queryKey: string | null) => ({
  kind: 'layout' as const,
  type: 'admin.resource-index',
  resource: slug,
  ...(queryKey ? { queryKey } : {}),
  items: [],
})

const TwoLists = defineComponent({
  name: 'TwoLists',
  setup: () => () =>
    h('div', [
      h(ResourceIndexLayout, { resource: 'contracts', queryKey: 'contracts' }),
      h(ResourceIndexLayout, { resource: 'quotes', queryKey: 'quotes' }),
    ]),
})

describe('several resource lists on one screen', () => {
  let mock: MockAdapter
  let router: Router
  let calls: Array<{ url: string; body: Record<string, unknown> }>

  beforeEach(async () => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    calls = []
    mock.onPost(/\/(contracts|quotes)\/search/).reply((config) => {
      calls.push({ url: String(config.url), body: JSON.parse(String(config.data ?? '{}')) })
      return [200, page([{ id: 1, title: 'One' }, { id: 2, title: 'Two' }])]
    })
    seedManifest()
    clearRegistry()
    registerLayout('admin.resource-index', ResourceIndexLayout as never)
    router = mkRouter()
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    clearRegistry()
  })

  it('keeps the search, filters, page and selection of each list apart, each under its own URL keys', async () => {
    await router.push('/screens/product?contracts.q=alpha&quotes.filter[status]=new&tab=x')
    await router.isReady()
    const w = mount(TwoLists, { global: { plugins: [router] }, attachTo: document.body })
    await flushPromises()

    const contracts = resourceIndexStoreFor('screen:contracts')
    const quotes = resourceIndexStoreFor('screen:quotes')
    expect(contracts).not.toBe(quotes)
    // Each read its own keys only.
    expect(contracts.search).toBe('alpha')
    expect(contracts.filters).toEqual({})
    expect(quotes.search).toBe('')
    expect(quotes.filters).toEqual({ status: 'new' })
    // And asked the server with them.
    expect(calls.find((c) => c.url.includes('contracts'))?.body.q).toBe('alpha')
    expect(calls.find((c) => c.url.includes('quotes'))?.body.q).toBeUndefined()
    expect(calls.find((c) => c.url.includes('quotes'))?.body.filters).toEqual({ status: 'new' })

    // A change in one list touches only its keys; the other list's and the tab's stay.
    await quotes.setSearch('beta')
    contracts.toggleRow(1)
    await flushPromises()
    const query = router.currentRoute.value.query
    expect(query['quotes.q']).toBe('beta')
    expect(query['contracts.q']).toBe('alpha')
    expect(query['quotes.filter[status]']).toBe('new')
    expect(query.tab).toBe('x')
    expect(query.q).toBeUndefined()
    expect(contracts.search).toBe('alpha')
    expect(contracts.selectedCount).toBe(1)
    expect(quotes.selectedCount).toBe(0)
    // The resource pages' shared state is not touched.
    expect(useResourceIndexStore().slug).toBeNull()
    w.unmount()
  })

  it('a single list without a key keeps the plain URL keys', async () => {
    await router.push('/screens/product?q=alpha')
    await router.isReady()
    const w = mount(ResourceIndexLayout, { props: { resource: 'contracts' }, global: { plugins: [router] } })
    await flushPromises()
    const store = resourceIndexStoreFor('screen:@contracts')
    expect(store.search).toBe('alpha')
    await store.setPage(2)
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ q: 'alpha', page: '2' })
    w.unmount()
  })

  it('a list in a closed tab loads nothing until its tab is opened, and the open tab goes to the URL', async () => {
    await router.push('/screens/product')
    await router.isReady()
    const w = mount(TabsLayout, {
      props: {
        queryKey: 'tab',
        items: [
          { key: 'contracts', label: 'Договоры', items: [listNode('contracts', 'contracts')] },
          { key: 'quotes', label: 'Котировки', items: [listNode('quotes', 'quotes')] },
        ],
      },
      global: { plugins: [router] },
      attachTo: document.body,
    })
    await flushPromises()
    expect(calls.map((c) => c.url)).toEqual(['/contracts/search'])

    await w.findAll('[role="tab"]')[1]!.trigger('click')
    await flushPromises()
    expect(calls.map((c) => c.url)).toEqual(['/contracts/search', '/quotes/search'])
    expect(router.currentRoute.value.query.tab).toBe('quotes')

    // Back to the first tab: its list keeps what it had — the state is its own.
    resourceIndexStoreFor('screen:contracts').toggleRow(2)
    await w.findAll('[role="tab"]')[0]!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.tab).toBe('contracts')
    expect(resourceIndexStoreFor('screen:contracts').selectedCount).toBe(1)
    w.unmount()
  })

  it('opens the tab the URL names, and follows the URL to another one', async () => {
    await router.push('/screens/product?tab=quotes')
    await router.isReady()
    const w = mount(TabsLayout, {
      props: {
        queryKey: 'tab',
        orientation: 'vertical',
        items: [
          { key: 'contracts', label: 'Договоры', items: [listNode('contracts', 'contracts')] },
          { key: 'quotes', label: 'Котировки', items: [listNode('quotes', 'quotes')] },
        ],
      },
      global: { plugins: [router] },
      attachTo: document.body,
    })
    await flushPromises()
    expect(w.find('.uid-tabs--vertical').exists()).toBe(true)
    expect(w.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')
    expect(calls.map((c) => c.url)).toEqual(['/quotes/search'])

    await router.push('/screens/product?tab=contracts')
    await flushPromises()
    expect(w.findAll('[role="tab"]')[0]!.attributes('aria-selected')).toBe('true')
    expect(calls.map((c) => c.url)).toEqual(['/quotes/search', '/contracts/search'])
    w.unmount()
  })

  it('opens the default tab by index when the URL names none', async () => {
    await router.push('/screens/product')
    await router.isReady()
    const w = mount(TabsLayout, {
      props: {
        default: 1,
        items: [
          { label: 'One', items: [] },
          { label: 'Two', items: [] },
        ],
      },
      global: { plugins: [router] },
    })
    await flushPromises()
    expect(w.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')
    w.unmount()
  })
})

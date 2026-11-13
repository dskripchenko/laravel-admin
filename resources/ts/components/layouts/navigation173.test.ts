import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import TabsLayout from './TabsLayout.vue'
import EmbeddedScreenLayout from './EmbeddedScreenLayout.vue'
import { registerLayout, clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { createAdminClient } from '../../api/client'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { useUserNavStore } from '../../stores/userNav'
import { useScreenStore } from '../../stores/screen'
import { resourceIndexStoreFor } from '../../stores/resourceIndex'
import StatWidget from '../dashboard/StatWidget.vue'
import WidgetRenderer from '../dashboard/WidgetRenderer.vue'
import { clearWidgetRegistry } from '../dashboard/registry'
import { registerBuiltinWidgets } from '../dashboard/builtin'
import MaskedCell from '../resource/MaskedCell.vue'

const Stub = defineComponent({ render: () => h('div') })
const Probe = defineComponent({ setup: () => () => h('p', { class: 'probe' }, 'x') })

function mkRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: Stub },
      { path: '/screens/q', component: Stub },
      { path: '/r/orders', component: Stub },
    ],
  })
}

describe('TabsLayout: badges and the open tab for the breadcrumbs (1.73)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearRegistry()
    registerLayout('rows', Probe)
  })

  it('draws a tab badge, red for danger, 99+ above 99', async () => {
    const router = mkRouter()
    await router.push('/screens/q?tab=all')
    await router.isReady()
    const w = mount(TabsLayout, {
      props: {
        queryKey: 'tab',
        items: [
          { key: 'pending', label: 'Не переданы', badge: 81, badgeTone: 'danger', items: [{ type: 'rows' }] },
          { key: 'all', label: 'Все', badge: 1500, items: [{ type: 'rows' }] },
          { key: 'none', label: 'Без', items: [{ type: 'rows' }] },
        ],
      },
      global: { plugins: [router] },
    })
    const pending = w.find('[data-testid="tab-badge-pending"]')
    expect(pending.text()).toBe('81')
    expect(pending.classes()).toContain('admin-tab-badge--danger')
    expect(w.find('[data-testid="tab-badge-all"]').text()).toBe('99+')
    expect(w.find('[data-testid="tab-badge-none"]').exists()).toBe(false)

    expect(useUserNavStore().openTabs).toEqual([{ queryKey: 'tab', label: 'Все', depth: 0 }])
    w.unmount()
    expect(useUserNavStore().openTabs).toEqual([])
  })
})

describe('resourceIndex store: ranges in the address, defaults, scope (1.73)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('reads and writes a date range as filter[x][from] / filter[x][to]', () => {
    const index = resourceIndexStoreFor('t:range')
    index.setSlug('orders')
    expect(index.fromQuery({ 'filter[created_at][from]': '2026-10-01', 'filter[created_at][to]': '2026-10-31', 'filter[status]': 'new' })).toBe(true)
    expect(index.filters).toEqual({ created_at: { from: '2026-10-01', to: '2026-10-31' }, status: 'new' })
    expect(index.toQuery('orders.')).toEqual({
      'orders.filter[created_at][from]': '2026-10-01',
      'orders.filter[created_at][to]': '2026-10-31',
      'orders.filter[status]': 'new',
    })
  })

  it('opens with the default filters, and a cleared list stays cleared', () => {
    const index = resourceIndexStoreFor('t:defaults')
    index.setSlug('orders')
    index.configureList({ scope: 'pending', defaultFilters: { status: 'new' } })
    index.fromQuery({})
    expect(index.filters).toEqual({ status: 'new' })
    expect(index.queryParams()).toEqual({ filters: { status: 'new' }, scope: 'pending' })

    index.filters = {}
    expect(index.toQuery()).toEqual({ filter: 'none' })
    index.resetQueryState()
    index.fromQuery({ filter: 'none' })
    expect(index.filters).toEqual({})
    expect(index.queryParams()).toEqual({ scope: 'pending' })

    index.resetQueryState()
    index.fromQuery({ 'filter[status]': 'done' })
    expect(index.filters).toEqual({ status: 'done' })
  })
})

describe('EmbeddedScreenLayout (1.73)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    clearRegistry()
    registerBuiltinComponents()
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
  })
  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('loads the screen by its own endpoint with its params, in a store of its own', async () => {
    let params: unknown = null
    mock.onGet('/captcha/state').reply((config) => {
      params = config.params
      return [200, { success: true, payload: { state: { key: 'abc' }, name: 'Капча', description: null, layout: [], command_bar: [], permissions: [], etag: 'e' } }]
    })
    const page = useScreenStore()
    page.name = 'Настройки сайта'
    const router = mkRouter()
    await router.push('/screens/q?tab=captcha')
    await router.isReady()
    const w = mount(EmbeddedScreenLayout, {
      props: { screen: 'captcha', params: { mode: 'v3' } },
      global: { plugins: [router] },
    })
    await flushPromises()

    expect(params).toEqual({ mode: 'v3' })
    expect(w.find('[data-testid="screen-captcha"]').classes()).toContain('admin-screen-page--embedded')
    expect(w.find('h2').text()).toBe('Капча')
    expect(w.find('h1').exists()).toBe(false)
    // The page's own screen is untouched.
    expect(page.name).toBe('Настройки сайта')
  })
})

describe('StatWidget and WidgetRenderer links (1.73)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearWidgetRegistry()
    registerBuiltinWidgets()
  })

  it('makes a card with a url a link and shows the previous period', async () => {
    const router = mkRouter()
    await router.push('/')
    await router.isReady()
    const w = mount(StatWidget, {
      props: {
        title: 'Продажи',
        stats: [
          { label: 'Заявки', value: 12, url: '/r/orders?filter%5Bcreated_at%5D%5Bfrom%5D=2026-10-01', previous: 10 },
          { label: 'Отказы', value: 3 },
        ],
      },
      global: { plugins: [router] },
    })
    const links = w.findAll('[data-testid="stat-link"]')
    expect(links).toHaveLength(1)
    expect(links[0]!.attributes('href')).toContain('/r/orders')
    expect(w.find('[data-testid="stat-previous"]').text()).toContain('10')
  })

  it('puts an arrow link on a widget with Widget::link()', async () => {
    const router = mkRouter()
    await router.push('/')
    await router.isReady()
    const w = mount(WidgetRenderer, {
      props: { node: { type: 'stats', link: '/r/orders', data: { stats: [{ label: 'A', value: 1 }] } } },
      global: { plugins: [router] },
    })
    expect(w.find('[data-testid="widget-link"]').attributes('href')).toBe('/r/orders')
    expect(w.attributes('link')).toBeUndefined()
  })
})

describe('MaskedCell (1.73)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
  })
  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('shows the mask, asks for the value on «Show» and hides it again', async () => {
    let body: unknown = null
    mock.onPost('/clients/reveal').reply((config) => {
      body = JSON.parse(config.data)
      return [200, { success: true, payload: { value: '+7 999 123-45-67' } }]
    })
    const w = mount(MaskedCell, { props: { value: '+7 *** ***-**-67', resourceSlug: 'clients', rowId: 5, column: 'phone', revealable: true } })
    expect(w.text()).toContain('+7 *** ***-**-67')
    await w.find('[data-testid="masked-reveal"]').trigger('click')
    await flushPromises()
    expect(body).toEqual({ id: 5, column: 'phone' })
    expect(w.text()).toContain('+7 999 123-45-67')
    await w.find('[data-testid="masked-hide"]').trigger('click')
    expect(w.text()).toContain('+7 *** ***-**-67')
  })

  it('offers no «Show» without the permission', () => {
    const w = mount(MaskedCell, { props: { value: 'a***@b.ru', resourceSlug: 'clients', rowId: 5, column: 'email' } })
    expect(w.find('[data-testid="masked-reveal"]').exists()).toBe(false)
  })
})

describe('DashboardPage: a range of dates and the comparison (1.73)', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    clearWidgetRegistry()
    registerBuiltinWidgets()
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
  })
  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('applies a range and asks the widgets with the comparison', async () => {
    const { useManifestStore } = await import('../../stores/manifest')
    const { default: DashboardPage } = await import('../dashboard/DashboardPage.vue')
    useManifestStore().manifest = {
      version: 'v1', locale: 'ru', resources: [], screens: [], settings: [], plugins: [], permissions: [],
      dashboards: [{
        slug: 'desk', label: 'Рабочий стол', widgets: [],
        periods: ['today', '7d', 'quarter'], period: '7d',
        periodLabels: { today: 'Сегодня', '7d': 'За 7 дней', quarter: 'Этот квартал' },
        customRange: true, comparable: true,
      }] as never,
    }
    mock.onGet('/dashboard/get').reply(200, { success: true, payload: { layout: null, period: null } })
    mock.onPost('/dashboard/savePeriod').reply(200, { success: true, payload: {} })
    const asked: string[] = []
    mock.onGet(/\/dashboard\/widgets/).reply((config) => {
      asked.push(String(config.url))
      return [200, { success: true, payload: { widgets: [], period: 'x' } }]
    })
    const w = mount(DashboardPage, { props: { slug: 'desk' }, global: { stubs: { teleport: true } } })
    await flushPromises()

    expect(w.find('[data-testid="dash-period"]').text()).toContain('За 7 дней')
    await w.find('[data-testid="dash-compare"]').trigger('click')
    await flushPromises()
    expect(asked.at(-1)).toContain('period=7d')
    expect(asked.at(-1)).toContain('compare=1')

    await w.find('[data-testid="dash-period"]').trigger('click')
    await flushPromises()
    const item = document.querySelector('[data-testid="dash-period-range"]') ?? w.find('[data-testid="dash-period-range"]').element
    ;(item as HTMLElement).click()
    await flushPromises()
    expect(w.find('[data-testid="dash-range"]').exists()).toBe(true)
    await w.find('[data-testid="dash-range-from"]').setValue('2026-01-01')
    await w.find('[data-testid="dash-range-to"]').setValue('2026-01-31')
    await w.find('[data-testid="dash-range-apply"]').trigger('click')
    await flushPromises()
    expect(asked.at(-1)).toContain(`period=${encodeURIComponent('2026-01-01..2026-01-31')}`)
    expect(w.find('[data-testid="dash-period"]').text()).toContain('01.01.2026 – 31.01.2026')
  })
})

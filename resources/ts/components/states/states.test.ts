/**
 * The states of 1.74: an empty list in its own words, «nothing found» with
 * the applied filters, a failed load with the request's code, no access with
 * the section and the key.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import AdminState from './AdminState.vue'
import ForbiddenPage from '../ForbiddenPage.vue'
import ResourceIndexPage from '../resource/ResourceIndexPage.vue'
import { filterSummary } from '../resource/filterSummary'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { ApiError, ForbiddenError, forbiddenPermission, stampError } from '../../api/errors'
import { useManifestStore } from '../../stores/manifest'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/forbidden', name: 'admin.forbidden', component: ForbiddenPage },
      { path: '/r/cities', name: 'admin.resource.cities.index', component: Stub },
      { path: '/r/cities/create', name: 'admin.resource.cities.create', component: Stub },
    ],
  })

function seed(overrides: Record<string, unknown> = {}): void {
  useManifestStore().manifest = {
    version: 'v1',
    locale: 'ru',
    resources: [{
      slug: 'cities',
      label: 'Города',
      permissions: { view: 'admin.cities.view' },
      fields: [],
      columns: [{ type: 'text', key: 'name', label: 'Название' }],
      filters: [
        { name: 'status', label: 'Статус', type: 'options', options: [{ value: 'error', label: 'ошибка' }] },
        { name: 'created_at', label: 'Создано', type: 'date_range' },
      ],
      actions: [],
      searchable: [],
      with: [],
      features: {},
      ...overrides,
    }],
    screens: [],
    settings: [],
    dashboards: [],
    plugins: [],
    permissions: [],
  }
}

const empty = { success: true, payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } } }

describe('filterSummary', () => {
  it('names each applied filter with its value', () => {
    const metas = [
      { name: 'status', label: 'Статус', options: [{ value: 'error', label: 'ошибка' }] },
      { name: 'created_at', label: 'Создано' },
    ]
    expect(filterSummary({ status: 'error', created_at: { from: '2026-11-01', to: '2026-11-13' } }, metas))
      .toBe('Статус «ошибка», Создано 01.11–13.11')
    expect(filterSummary({ product: ['vzr', 'ifl'] }, [], 'Иванов')).toBe('«Иванов», product «vzr, ifl»')
    expect(filterSummary({ status: '' }, metas)).toBe('')
  })
})

describe('AdminState', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the request code of a failed load and retries', async () => {
    const err = stampError(new ApiError(500, { errorKey: 'x', message: 'КИАС не ответил за 30 с.' }), '7f3a-91c2')
    const w = mount(AdminState, { props: { kind: 'error', title: 'Не удалось загрузить договоры', error: err } })
    expect(w.text()).toContain('Не удалось загрузить договоры')
    expect(w.text()).toContain('КИАС не ответил за 30 с.')
    expect(w.get('[data-testid="state-code"]').text()).toContain('req 7f3a-91c2')
    await w.get('[data-testid="state-retry"]').trigger('click')
    expect(w.emitted('retry')).toHaveLength(1)
  })

  it('copies the code', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    const err = stampError(new ApiError(500, { errorKey: 'x', message: 'x' }), 'abcd-0001')
    const w = mount(AdminState, { props: { kind: 'error', error: err } })
    await w.get('[data-testid="state-copy-code"]').trigger('click')
    expect(writeText.mock.calls[0][0]).toContain('req abcd-0001')
  })

  it('names the section and the key of a closed one', () => {
    const err = new ForbiddenError({ errorKey: 'forbidden', message: 'Доступ запрещён: platform.audit-logs.view', permission: 'platform.audit-logs.view' })
    const w = mount(AdminState, { props: { kind: 'forbidden', section: 'Журнал аудита', error: err } })
    expect(w.text()).toContain('Раздел «Журнал аудита» недоступен')
    expect(w.get('[data-testid="state-permission"]').text()).toBe('platform.audit-logs.view')
    expect(w.find('[data-testid="state-copy-key"]').exists()).toBe(true)
  })

  it('reads the key out of an older server message', () => {
    const err = new ForbiddenError({ errorKey: 'forbidden', message: 'Доступ запрещён: admin.users.view' })
    expect(forbiddenPermission(err)).toBe('admin.users.view')
  })
})

describe('ForbiddenPage', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('takes the section and the key from the guard', async () => {
    const router = mkRouter()
    await router.push({ name: 'admin.forbidden', query: { section: 'Роли', permission: 'admin.roles.view' } })
    const w = mount(ForbiddenPage, { global: { plugins: [router] } })
    expect(w.text()).toContain('Раздел «Роли» недоступен')
    expect(w.text()).toContain('admin.roles.view')
    expect(w.find('[data-testid="state-home"]').exists()).toBe(true)
  })
})

describe('ResourceIndexPage states', () => {
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

  async function mountList(props: Record<string, unknown> = {}, path = '/r/cities') {
    const router = mkRouter()
    await router.push(path)
    await router.isReady()
    const w = mount(ResourceIndexPage, { props: { slug: 'cities', ...props }, global: { plugins: [router] } })
    await flushPromises()
    return w
  }

  it('says what Resource::emptyState() tells it, with the create button under its caption', async () => {
    seed({ empty_state: { title: 'Городов пока нет', description: 'Города используются в карточках офисов.', action: { create: 'Добавить город' } } })
    mock.onPost('/cities/search').reply(200, empty)
    const w = await mountList()
    const state = w.get('[data-testid="resource-empty"]')
    expect(state.text()).toContain('Городов пока нет')
    expect(state.text()).toContain('Города используются в карточках офисов.')
    expect(w.get('[data-testid="resource-empty-create"]').text()).toContain('Добавить город')
  })

  it('takes the layout\'s empty state over the resource\'s, and an action of its own', async () => {
    seed({ empty_state: { title: 'Городов пока нет', action: null } })
    mock.onPost('/cities/search').reply(200, empty)
    const w = await mountList({
      emptyState: {
        title: 'Новых звонков нет',
        description: 'Счётчик в меню обнулён.',
        action: { kind: 'action', name: 'open-work', label: 'Открыть «В работе»', type: 'link', attributes: { href: '/r/cities' } },
      },
    })
    expect(w.text()).toContain('Новых звонков нет')
    expect(w.find('[data-testid="resource-empty-create"]').exists()).toBe(false)
    expect(w.get('[data-testid="resource-empty-action"]').text()).toContain('Открыть «В работе»')
  })

  it('offers no action with action: false', async () => {
    seed({ empty_state: { title: 'Пусто', action: false } })
    mock.onPost('/cities/search').reply(200, empty)
    const w = await mountList()
    expect(w.find('[data-testid="resource-empty-create"]').exists()).toBe(false)
    expect(w.find('[data-testid="resource-empty-action"]').exists()).toBe(false)
  })

  it('defaults to «Записей нет» and «Создать»', async () => {
    seed()
    mock.onPost('/cities/search').reply(200, empty)
    const w = await mountList()
    expect(w.get('[data-testid="resource-empty"]').text()).toContain('Записей нет')
    expect(w.get('[data-testid="resource-empty-create"]').text()).toContain('Создать')
  })

  it('lists the applied filters when nothing is found', async () => {
    seed()
    mock.onPost('/cities/search').reply(200, empty)
    const w = await mountList({}, '/r/cities?filter[status]=error')
    const state = w.get('[data-testid="resource-no-results"]')
    expect(state.text()).toContain('Ничего не найдено')
    expect(state.text()).toContain('Статус «ошибка»')
    expect(w.get('[data-testid="resource-reset-filters"]').text()).toContain('Сбросить фильтры')
  })

  it('shows the request code when the list fails', async () => {
    seed()
    mock.onPost('/cities/search').reply(500, { success: false, payload: { errorKey: 'x', message: 'Сервис не ответил.' } })
    const w = await mountList()
    const state = w.get('[data-testid="resource-error"]')
    expect(state.text()).toContain('Сервис не ответил.')
    expect(state.get('[data-testid="state-code"]').text()).toMatch(/^req [0-9a-f]{4}-[0-9a-f]{4}/)
    expect(mock.history.post[0].headers?.['X-Request-Id']).toMatch(/^[0-9a-f]{4}-[0-9a-f]{4}$/)
  })

  it('names the missing key on a 403', async () => {
    seed()
    mock.onPost('/cities/search').reply(403, { success: false, payload: { errorKey: 'forbidden', message: 'Доступ запрещён: admin.cities.view', permission: 'admin.cities.view' } })
    const w = await mountList()
    const state = w.get('[data-testid="resource-forbidden"]')
    expect(state.text()).toContain('Раздел «Города» недоступен')
    expect(state.text()).toContain('admin.cities.view')
  })
})

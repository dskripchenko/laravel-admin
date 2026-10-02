import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import ScreenPage from './ScreenPage.vue'
import { setAdminClient, clearAdminClient } from '../stores/registry'
import { createAdminClient } from '../api/client'
import { useScreenStore } from '../stores/screen'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const commandBar = [
  {
    kind: 'action',
    name: 'more',
    label: 'Ещё',
    type: 'dropdown',
    position: ['command_bar'],
    confirm: null,
    attributes: {},
    items: [
      {
        kind: 'action',
        name: 'recalc',
        label: 'Пересчитать',
        type: 'button',
        position: ['command_bar'],
        confirm: { title: 'Пересчёт', message: 'Пересчитать статистику?' },
        attributes: { method: 'recalc' },
      },
      {
        kind: 'action',
        name: 'reports',
        label: 'Отчёты',
        type: 'link',
        position: ['command_bar'],
        confirm: null,
        attributes: { href: '/reports' },
      },
    ],
  },
]

async function mountScreen(path = '/s/stats') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/s/:slug', component: Stub },
      { path: '/reports', component: Stub },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(ScreenPage, {
    props: { slug: 'stats' },
    global: { plugins: [router] },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router }
}

describe('ScreenPage command bar', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    mock.onGet('/stats/state').reply(200, {
      success: true,
      payload: {
        state: { period: 'week' },
        name: 'Статистика',
        description: null,
        layout: [],
        command_bar: commandBar,
        permissions: [],
        etag: 'e1',
      },
    })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('dispatches a dropdown item through the confirm dialog to runMethod', async () => {
    const calls: unknown[] = []
    mock.onPost('/stats/runMethod').reply((config) => {
      calls.push(JSON.parse(config.data))
      return [200, { success: true, payload: { message: 'Готово' } }]
    })
    const { wrapper } = await mountScreen()

    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-recalc"]') as HTMLElement).click()
    await flushPromises()
    expect(document.body.querySelector('[data-testid="action-confirm-message"]')?.textContent)
      .toContain('Пересчитать статистику?')
    ;(document.body.querySelector('[data-testid="action-confirm-ok"]') as HTMLElement).click()
    await flushPromises()

    expect(calls).toEqual([{ method: 'recalc', payload: { period: 'week' } }])
    expect(wrapper.text()).toContain('Готово')
    wrapper.unmount()
  })

  it('a refused screen method shows the server reason in the alert', async () => {
    mock.onPost('/stats/runMethod').reply(422, {
      success: false,
      payload: { errorKey: 'action_failed', message: 'SMTP server is down' },
    })
    const { wrapper } = await mountScreen()

    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-recalc"]') as HTMLElement).click()
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-confirm-ok"]') as HTMLElement).click()
    await flushPromises()

    const alert = wrapper.find('.admin-screen-page__alert')
    expect(alert.text()).toBe('SMTP server is down')
    expect(wrapper.text()).not.toContain('Не удалось выполнить действие')
    wrapper.unmount()
  })

  it('a forbidden screen method shows the 403 reason in the alert', async () => {
    mock.onPost('/stats/runMethod').reply(403, {
      success: false,
      payload: { errorKey: 'action_forbidden', message: 'Access denied: admin.stats.recalc' },
    })
    const { wrapper } = await mountScreen()

    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-recalc"]') as HTMLElement).click()
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-confirm-ok"]') as HTMLElement).click()
    await flushPromises()

    expect(wrapper.find('.admin-screen-page__alert').text()).toBe('Access denied: admin.stats.recalc')
    wrapper.unmount()
  })

  it('navigates a link item through the router', async () => {
    const { wrapper, router } = await mountScreen()
    const push = vi.spyOn(router, 'push')

    await wrapper.find('[data-testid="action-more"]').trigger('click')
    await flushPromises()
    ;(document.body.querySelector('[data-testid="action-reports"]') as HTMLElement).click()
    await flushPromises()

    expect(push).toHaveBeenCalledWith('/reports')
    wrapper.unmount()
  })
})

describe('ScreenPage query string', () => {
  let mock: MockAdapter
  const seen: unknown[] = []

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    seen.length = 0
    mock.onGet('/stats/state').reply((config) => {
      seen.push(config.params)
      return [200, {
        success: true,
        payload: {
          state: {}, name: 'Статистика', description: null, layout: [],
          command_bar: [], permissions: [], etag: 'e1',
        },
      }]
    })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('passes the page query to the state action', async () => {
    const { wrapper } = await mountScreen('/s/stats?period=30&tag=a&tag=b')
    expect(seen).toEqual([{ period: '30', tag: ['a', 'b'] }])
    wrapper.unmount()
  })

  it('reloads the snapshot when the query changes, and keeps it on refresh', async () => {
    mock.onPost('/stats/runMethod').reply(200, { success: true, payload: { refresh: true } })
    const { wrapper, router } = await mountScreen('/s/stats?tab=one')
    await router.push('/s/stats?tab=two')
    await flushPromises()
    expect(seen).toEqual([{ tab: 'one' }, { tab: 'two' }])

    await useScreenStore().runMethod('recalc')
    await flushPromises()
    expect(seen.at(-1)).toEqual({ tab: 'two' })
    wrapper.unmount()
  })
})

describe('ScreenPage links under a panel base', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    mock.onGet('/stats/state').reply(200, {
      success: true,
      payload: {
        state: {},
        name: 'Статистика',
        description: null,
        layout: [],
        command_bar: [
          { kind: 'action', name: 'go', label: 'Go', type: 'button', position: ['command_bar'], confirm: null, attributes: { method: 'go' } },
        ],
        permissions: [],
        etag: 'e1',
      },
    })
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  async function mountUnderBase() {
    const router = createRouter({
      history: createMemoryHistory('/admin'),
      routes: [
        { path: '/s/:slug', component: Stub },
        { path: '/screens/:slug', component: Stub },
      ],
    })
    await router.push('/s/stats')
    await router.isReady()
    const wrapper = mount(ScreenPage, {
      props: { slug: 'stats' },
      global: { plugins: [router] },
      attachTo: document.body,
    })
    await flushPromises()
    return { wrapper, router }
  }

  it('strips the panel prefix from message_link — no /admin/admin/…', async () => {
    mock.onPost('/stats/runMethod').reply(200, {
      success: true,
      payload: { message: 'Запущено', message_link: { url: '/admin/screens/jobs', label: 'Открыть' } },
    })
    const { wrapper, router } = await mountUnderBase()

    await wrapper.find('[data-testid="action-go"]').trigger('click')
    await flushPromises()
    const link = wrapper.find('.admin-screen-page__alert-link')
    expect(link.attributes('href')).toBe('/admin/screens/jobs')

    await link.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/screens/jobs')
    wrapper.unmount()
  })

  it('strips the panel prefix from redirect_url the same way', async () => {
    mock.onPost('/stats/runMethod').reply(200, {
      success: true,
      payload: { redirect_url: '/admin/screens/jobs' },
    })
    const { wrapper, router } = await mountUnderBase()

    await wrapper.find('[data-testid="action-go"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/screens/jobs')
    wrapper.unmount()
  })
})

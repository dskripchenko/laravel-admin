import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import StatusIndicators from './StatusIndicators.vue'
import { setAdminClient, clearAdminClient } from '../../../stores/registry'
import { createAdminClient } from '../../../api/client'

const global = { stubs: { RouterLink: RouterLinkStub } }

describe('StatusIndicators', () => {
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
    vi.useRealTimers()
  })

  function reply(indicators: unknown[]): void {
    mock.onGet('/system/status').reply(200, { success: true, payload: { indicators } })
  }

  it('draws an indicator that is not ok', async () => {
    reply([{ key: 'admin.health', status: 'error', label: 'Проверки', detail: '2 из 5 не прошли' }])
    const wrapper = mount(StatusIndicators, { global })
    await flushPromises()

    const item = wrapper.find('[data-testid="status-admin.health"]')
    expect(item.exists()).toBe(true)
    expect(item.attributes('data-status')).toBe('error')
    expect(wrapper.text()).toContain('Проверки')
  })

  it('stays silent when everything is ok', async () => {
    reply([{ key: 'admin.health', status: 'ok', label: 'Проверки' }])
    const wrapper = mount(StatusIndicators, { global })
    await flushPromises()

    // A green dot per plugin is decoration nobody reads — the header speaks
    // only when something is off.
    expect(wrapper.find('.admin-topbar__status').exists()).toBe(false)
  })

  it('routes an in-panel url through the router', async () => {
    reply([{ key: 'admin.health', status: 'warning', label: 'Проверки', url: '/r/health' }])
    const wrapper = mount(StatusIndicators, { global })
    await flushPromises()

    // A router-link rather than an href: a full reload would throw away the
    // panel's state over a hint in the header.
    expect(wrapper.findComponent(RouterLinkStub).props('to')).toBe('/r/health')
  })

  it('renders an external url as a plain link', async () => {
    reply([{ key: 'ext', status: 'warning', label: 'Статус', url: 'https://status.example.com' }])
    const wrapper = mount(StatusIndicators, { global })
    await flushPromises()

    const link = wrapper.find('a[href="https://status.example.com"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('target')).toBe('_blank')
  })

  it('says nothing when the endpoint fails', async () => {
    mock.onGet('/system/status').reply(500)
    const wrapper = mount(StatusIndicators, { global })
    await flushPromises()

    // Every deploy restart makes this call fail; a header that cries wolf then
    // is a header people learn to ignore.
    expect(wrapper.find('.admin-topbar__status').exists()).toBe(false)
  })

  it('re-asks on a timer and drops it on unmount', async () => {
    vi.useFakeTimers()
    reply([{ key: 'admin.health', status: 'warning', label: 'Проверки' }])
    const wrapper = mount(StatusIndicators, { global })
    await vi.advanceTimersByTimeAsync(0)
    expect(mock.history.get.length).toBe(1)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(mock.history.get.length).toBe(2)

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(120_000)
    expect(mock.history.get.length).toBe(2)
  })
})

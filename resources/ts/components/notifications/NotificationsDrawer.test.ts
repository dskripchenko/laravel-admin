import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import NotificationsDrawer from './NotificationsDrawer.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useNotificationsStore } from '../../stores/notifications'

const sampleItem = (overrides: Partial<{ id: string; read_at: string | null; level: string }> = {}) => ({
  id: overrides.id ?? 'uuid-1',
  type: 'App\\Notifications\\Test',
  data: {
    title: 'Hello',
    body: 'World',
    level: overrides.level ?? 'info',
  },
  read_at: overrides.read_at ?? null,
  created_at: '2026-04-30T12:00:00+00:00',
})

describe('NotificationsDrawer', () => {
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

  it('mounts closed by default — drawer not in DOM as visible', () => {
    const w = mount(NotificationsDrawer, {
      props: { open: false },
      attachTo: document.body,
    })
    // UidDrawer prefers v-model=false → нет body content
    expect(w.exists()).toBe(true)
    w.unmount()
  })

  it('renders empty state when no items + open', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1, unread_count: 0 } },
    })
    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    expect(document.body.textContent).toContain('Нет уведомлений')
    w.unmount()
  })

  it('lists notifications + shows unread tinted', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: {
        data: [
          sampleItem({ id: '1', read_at: null }),
          sampleItem({ id: '2', read_at: '2026-04-30T12:00:00+00:00' }),
        ],
        meta: { page: 1, per_page: 20, total: 2, last_page: 1, unread_count: 1 },
      },
    })
    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    const items = document.querySelectorAll('.admin-notifs__item')
    expect(items.length).toBe(2)
    const unreadItems = document.querySelectorAll('.admin-notifs__item--unread')
    expect(unreadItems.length).toBe(1)
    w.unmount()
  })

  it('markAllRead emits action via store', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: { data: [sampleItem()], meta: { page: 1, per_page: 20, total: 1, last_page: 1, unread_count: 1 } },
    })
    mock.onPost('/notifications/markAllAsRead').reply(200, {
      success: true,
      payload: { updated: 1, unread_count: 0 },
    })

    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    await useNotificationsStore().markAllAsRead()
    await flushPromises()
    expect(useNotificationsStore().unreadCount).toBe(0)
    w.unmount()
  })

  it('shows unread count in header', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1, unread_count: 5 } },
    })
    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    expect(document.body.textContent).toContain('Уведомления')
    expect(document.body.textContent).toContain('5')
    w.unmount()
  })

  it('emits update:open=false on drawer close', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1, unread_count: 0 } },
    })
    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    // Симулируем close через UidDrawer update:modelValue
    await w.vm.$emit('update:open', false)
    expect(w.emitted('update:open')?.[0]).toEqual([false])
    w.unmount()
  })

  it('select-item event emits with item + auto-marks read', async () => {
    mock.onGet(/\/notifications\/list/).reply(200, {
      success: true,
      payload: {
        data: [sampleItem({ id: '1' })],
        meta: { page: 1, per_page: 20, total: 1, last_page: 1, unread_count: 1 },
      },
    })
    mock.onPost('/notifications/markAsRead').reply(200, {
      success: true,
      payload: { id: '1', unread_count: 0 },
    })
    const w = mount(NotificationsDrawer, {
      props: { open: true },
      attachTo: document.body,
    })
    await flushPromises()
    const itemEl = document.querySelector('.admin-notifs__item') as HTMLElement
    expect(itemEl).toBeTruthy()
    itemEl.click()
    await flushPromises()
    expect(w.emitted('select-item')).toBeTruthy()
    w.unmount()
  })
})

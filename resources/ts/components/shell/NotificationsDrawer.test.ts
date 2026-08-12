import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import NotificationsDrawer from './NotificationsDrawer.vue'
import { useNotificationsStore } from '../../stores/notifications'

/**
 * The notifications drawer — the LIVE one, the one AdminApp draws.
 *
 * This file's earlier test checked a DIFFERENT component of the same name, in
 * `components/notifications/`: exported outwards, rendered nowhere. The test
 * was green, the twin was dead, and an edit to it went into the void — which
 * is exactly what happened to the link to the full list.
 *
 * The markup is teleported into the body, so we check the document rather than
 * the wrapper.
 */
const push = vi.fn(async () => undefined)
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

const items = [
  { id: '1', type: 'x', data: { title: 'Новый клиент', body: 'ООО «Ромашка»' }, read_at: null, created_at: '2026-08-12T09:00:00Z' },
  { id: '2', type: 'x', data: { title: 'Готово' }, read_at: '2026-08-12T09:30:00Z', created_at: '2026-08-12T09:20:00Z' },
]

function openDrawer() {
  const store = useNotificationsStore()
  store.load = vi.fn(async () => undefined)
  store.markAsRead = vi.fn(async () => undefined)
  store.markAllAsRead = vi.fn(async () => undefined)
  store.destroy = vi.fn(async () => undefined)
  store.items = items as never
  store.unreadCount = 1
  store.isOpen = true

  return store
}

describe('NotificationsDrawer', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    push.mockClear()
  })
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('показывает список и отмечает непрочитанные', () => {
    openDrawer()
    mount(NotificationsDrawer, { attachTo: document.body })

    expect(document.body.textContent).toContain('Новый клиент')
    expect(document.querySelectorAll('.admin-notif-drawer__item')).toHaveLength(2)
    expect(document.querySelectorAll('.admin-notif-drawer__item--unread')).toHaveLength(1)
  })

  it('ведёт на полный список и закрывает себя', async () => {
    // Without the link the notifications page exists with no way to find it.
    const store = openDrawer()
    mount(NotificationsDrawer, { attachTo: document.body })

    const link = document.querySelector('.admin-notif-drawer__ft a') as HTMLAnchorElement | null
    expect(link).not.toBeNull()

    link?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    await Promise.resolve()

    expect(push).toHaveBeenCalledWith({ name: 'admin.notifications' })
    // A drawer left open over the page would cover the very thing one navigated for.
    expect(store.isOpen).toBe(false)
  })

  it('пустой список говорит об этом прямо', () => {
    const store = openDrawer()
    store.items = [] as never

    mount(NotificationsDrawer, { attachTo: document.body })

    expect(document.body.textContent).toContain('Нет уведомлений')
  })
})

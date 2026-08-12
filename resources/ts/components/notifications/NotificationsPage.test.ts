import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import NotificationsPage from './NotificationsPage.vue'
import { useNotificationsStore } from '../../stores/notifications'

/**
 * The notifications page.
 *
 * The `/notifications` route was registered only if the host passed a
 * component, and nobody did — the address gave a 404 in every panel. Nothing
 * linked there, so the hole never surfaced: the only way to notice it was to
 * type the address by hand.
 */
const items = [
  { id: '1', type: 'x', data: { title: 'Новый клиент', body: 'ООО «Ромашка»', level: 'info' }, read_at: null, created_at: '2026-08-12T09:00:00Z' },
  { id: '2', type: 'x', data: { title: 'Печать не удалась', level: 'danger' }, read_at: '2026-08-12T09:30:00Z', created_at: '2026-08-12T09:20:00Z' },
]

describe('NotificationsPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useNotificationsStore()
    store.load = vi.fn(async () => undefined)
    store.markAsRead = vi.fn(async () => undefined)
    store.markAllAsRead = vi.fn(async () => undefined)
    store.destroy = vi.fn(async () => undefined)
    store.items = items as never
    store.unreadCount = 1
  })

  it('показывает список и различает непрочитанные', () => {
    const w = mount(NotificationsPage)

    expect(w.text()).toContain('Новый клиент')
    expect(w.text()).toContain('Печать не удалась')
    expect(w.findAll('.admin-notifs-page__item--unread')).toHaveLength(1)
  })

  it('грузит список при открытии — иначе страница пуста до первого клика', () => {
    const store = useNotificationsStore()
    mount(NotificationsPage)

    expect(store.load).toHaveBeenCalledWith('all', 1)
  })

  it('переключение фильтра перечитывает список с первой страницы', async () => {
    const store = useNotificationsStore()
    const w = mount(NotificationsPage)
    await w.findAll('.admin-notifs-page__tab')[1].trigger('click')

    // From the first page specifically: staying on another filter's fifth page
    // is a sure way to show emptiness where there are records.
    expect(store.load).toHaveBeenCalledWith('unread', 1)
  })

  it('«прочитано» не дёргается на уже прочитанном', async () => {
    const store = useNotificationsStore()
    const w = mount(NotificationsPage)
    const buttons = w.findAll('.admin-notifs-page__item')[1].findAll('button')

    // A read notification is left with "remove" alone.
    expect(buttons).toHaveLength(1)
    expect(store.markAsRead).not.toHaveBeenCalled()
  })
})

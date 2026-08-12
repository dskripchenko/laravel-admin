import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import NotificationsDrawer from './NotificationsDrawer.vue'
import { useNotificationsStore } from '../../stores/notifications'

/**
 * Шторка уведомлений — ЖИВАЯ, та, которую рисует AdminApp.
 *
 * Прежний тест этого файла проверял ДРУГОЙ компонент с тем же именем, лежавший
 * в `components/notifications/`: он экспортировался наружу, но не рисовался
 * нигде. Тест был зелёным, двойник — мёртвым, и правка в него уходила в
 * пустоту. Так и случилось со ссылкой на полный список.
 *
 * Разметка телепортируется в body, поэтому проверяем документ, а не обёртку.
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
    // Без ссылки страница уведомлений существует, а найти её неоткуда.
    const store = openDrawer()
    mount(NotificationsDrawer, { attachTo: document.body })

    const link = document.querySelector('.admin-notif-drawer__ft a') as HTMLAnchorElement | null
    expect(link).not.toBeNull()

    link?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    await Promise.resolve()

    expect(push).toHaveBeenCalledWith({ name: 'admin.notifications' })
    // Открытая поверх страницы шторка перекрыла бы то, ради чего переходили.
    expect(store.isOpen).toBe(false)
  })

  it('пустой список говорит об этом прямо', () => {
    const store = openDrawer()
    store.items = [] as never

    mount(NotificationsDrawer, { attachTo: document.body })

    expect(document.body.textContent).toContain('Нет уведомлений')
  })
})

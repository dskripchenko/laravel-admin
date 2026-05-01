import { describe, it, expect, beforeEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import AdminSidebar from './AdminSidebar.vue'
import { useMenuStore } from '../../stores/menu'
import { useAuthStore } from '../../stores/auth'
import type { AdminBootstrap, AdminUser } from '../../types/bootstrap'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/screens/reports', name: 'admin.screen.reports', component: Stub },
    ],
  })

const mkUser = (): AdminUser => ({
  id: 1, name: 'A', email: 'a@a',
  avatar: null, locale: null, theme: null, twoFactorEnabled: false,
})
const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
  availableLocales: [], theme: 'light', availableThemes: [],
  brand: {}, user: null, permissions: [], manifestVersion: null,
  plugins: [], unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

async function mountSidebar() {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  return mount(AdminSidebar, {
    global: {
      plugins: [router],
      stubs: {
        RouterLink: RouterLinkStub,
      },
    },
  })
}

describe('AdminSidebar', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders nothing when menu is empty', async () => {
    const wrapper = await mountSidebar()
    expect(wrapper.findAll('.admin-sidebar__item')).toHaveLength(0)
  })

  it('renders visible items grouped', async () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    const menu = useMenuStore()
    menu.setItems([
      { key: 'users', label: 'Users', url: '/r/users', group: 'main' },
      { key: 'reports', label: 'Reports', routeName: 'admin.screen.reports', group: 'main' },
      { key: 'settings', label: 'Settings', url: '/settings/general' },
    ])
    const wrapper = await mountSidebar()
    const items = wrapper.findAll('.admin-sidebar__item')
    expect(items).toHaveLength(3)
    const headers = wrapper.findAll('.admin-sidebar__group-header').map((h) => h.text())
    expect(headers).toContain('main')
  })

  it('hides items by permission filter', async () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['admin.users.view'] }))
    const menu = useMenuStore()
    menu.setItems([
      { key: 'users', label: 'Users', url: '/r/users', permissions: ['admin.users.view'] },
      { key: 'posts', label: 'Posts', url: '/r/posts', permissions: ['admin.posts.view'] },
    ])
    const wrapper = await mountSidebar()
    const labels = wrapper.findAll('.admin-sidebar__label').map((l) => l.text())
    expect(labels).toEqual(['Users'])
  })

  it('renders badge when present', async () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    const menu = useMenuStore()
    menu.setItems([{ key: 'x', label: 'X', url: '/x', badge: 5 }])
    const wrapper = await mountSidebar()
    expect(wrapper.find('.admin-sidebar__badge').text()).toBe('5')
  })

  it('renders RouterLinkStub for routeName items', async () => {
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    const menu = useMenuStore()
    menu.setItems([{ key: 'r', label: 'R', routeName: 'admin.home' }])
    const wrapper = await mountSidebar()
    const link = wrapper.findComponent(RouterLinkStub)
    expect(link.exists()).toBe(true)
    expect(link.props('to')).toEqual({ name: 'admin.home' })
  })
})

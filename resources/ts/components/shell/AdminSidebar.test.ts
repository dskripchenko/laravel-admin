import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import AdminSidebar from './AdminSidebar.vue'
import { useMenuStore } from '../../stores/menu'
import { useAuthStore } from '../../stores/auth'
import type { AdminBootstrap, AdminUser } from '../../types/bootstrap'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

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

const mkRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/r/users', name: 'admin.resource.users.index', component: Stub },
    ],
  })

async function mountSidebar(props: Record<string, unknown> = {}) {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  return mount(AdminSidebar, {
    props,
    global: { plugins: [router] },
  })
}

describe('AdminSidebar', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders brand block with name + mark', async () => {
    const wrapper = await mountSidebar({ brandName: 'Acme', brandMark: 'A' })
    expect(wrapper.find('.admin-sidebar-brand__mark').text()).toBe('A')
    expect(wrapper.find('.admin-sidebar-brand__name').text()).toBe('Acme')
  })

  it('hides brand-name when collapsed=true (mark stays)', async () => {
    const wrapper = await mountSidebar({ collapsed: true })
    expect(wrapper.find('.admin-sidebar-brand__mark').exists()).toBe(true)
    expect(wrapper.find('.admin-sidebar-brand__name').exists()).toBe(false)
  })

  it('renders tenant block when provided + not collapsed', async () => {
    const wrapper = await mountSidebar({
      tenant: { label: 'Workspace', name: 'Acme Inc.' },
    })
    expect(wrapper.find('.admin-sidebar-tenant').text()).toContain('Workspace')
    expect(wrapper.find('.admin-sidebar-tenant').text()).toContain('Acme Inc.')
  })

  it('hides tenant when collapsed', async () => {
    const wrapper = await mountSidebar({
      collapsed: true,
      tenant: { label: 'W', name: 'X' },
    })
    expect(wrapper.find('.admin-sidebar-tenant').exists()).toBe(false)
  })

  it('renders menu items grouped (filtered by permissions)', async () => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['*'] }))
    const menu = useMenuStore()
    menu.setItems([
      { key: 'users', label: 'Users', url: '/r/users', group: 'Контент' },
      { key: 'reports', label: 'Reports', routeName: 'admin.home', group: 'Аналитика' },
    ])
    const wrapper = await mountSidebar()
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).toContain('Reports')
    expect(wrapper.text()).toContain('Контент')
    expect(wrapper.text()).toContain('Аналитика')
  })

  it('hides items by permission filter', async () => {
    setActivePinia(createPinia())
    const auth = useAuthStore()
    auth.hydrate(mkBootstrap({ user: mkUser(), permissions: ['admin.users.view'] }))
    const menu = useMenuStore()
    menu.setItems([
      { key: 'users', label: 'Users', url: '/r/users', permissions: ['admin.users.view'] },
      { key: 'posts', label: 'Posts', url: '/r/posts', permissions: ['admin.posts.view'] },
    ])
    const wrapper = await mountSidebar()
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).not.toContain('Posts')
  })

  it('renders footer with version and docs link', async () => {
    const wrapper = await mountSidebar({
      version: 'v2.4.1',
      docsUrl: 'https://docs.example.com',
    })
    expect(wrapper.find('.admin-sidebar-foot').text()).toContain('v2.4.1')
    expect(wrapper.find('.admin-sidebar-foot a').attributes('href')).toBe('https://docs.example.com')
  })
})

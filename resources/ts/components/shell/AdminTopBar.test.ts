import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import AdminTopBar from './AdminTopBar.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useLocaleStore } from '../../stores/locale'
import { useNotificationsStore } from '../../stores/notifications'
import type { AdminBootstrap, AdminUser } from '../../types/bootstrap'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkUser = (): AdminUser => ({
  id: 1, name: 'Alice Wonder', email: 'a@a',
  avatar: null, locale: null, theme: null, twoFactorEnabled: false,
})
const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
  availableLocales: ['ru', 'en'], theme: 'light', availableThemes: ['light', 'dark'],
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
      { path: '/notifications', name: 'admin.notifications', component: Stub },
      { path: '/profile', name: 'admin.profile', component: Stub },
      { path: '/login', name: 'admin.login', component: Stub },
    ],
  })

describe('AdminTopBar', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)

    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    useThemeStore().hydrate(mkBootstrap())
    useLocaleStore().hydrate(mkBootstrap())
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  async function mountBar(props: Record<string, unknown> = {}) {
    const router = mkRouter()
    await router.push('/')
    await router.isReady()
    return mount(AdminTopBar, {
      props,
      global: {
        plugins: [router],
        stubs: {
          RouterLink: RouterLinkStub,
          // LocaleSwitcher/UserMenu внутри используют UidMenu (popover/teleport),
          // что вешает jsdom. На уровне TopBar тестируем только нашу композицию,
          // оставляя только NotificationBell (он чистый router-link + badge,
          // без uid-Menu).
          LocaleSwitcher: { template: '<div class="stub-locale-switcher"><span data-icon="globe" /></div>' },
          UserMenu: { template: '<div class="stub-user-menu"></div>' },
        },
      },
    })
  }

  it('renders 4 widgets in actions area', async () => {
    const wrapper = await mountBar()
    // Topbar содержит ThemeToggle/LocaleSwitcher/NotificationBell/UserMenu —
    // ищем конкретные icon-attrs.
    expect(wrapper.find('[data-icon="bell"]').exists()).toBe(true)
    expect(wrapper.find('[data-icon="moon"]').exists()).toBe(true)
    expect(wrapper.find('[data-icon="globe"]').exists()).toBe(true)
  })

  it('renders breadcrumbs with current marker on last', async () => {
    const wrapper = await mountBar({
      breadcrumbs: [
        { label: 'Контент', to: '/' },
        { label: 'Articles', to: '/r/articles' },
        { label: 'Введение в Laravel 12' },
      ],
    })
    const crumbs = wrapper.findAll('.admin-topbar__breadcrumbs .cur, .admin-topbar__breadcrumbs span:not(.sep), .admin-topbar__breadcrumbs a')
    expect(wrapper.find('.admin-topbar__breadcrumbs').text()).toContain('Введение в Laravel 12')
    // Последний — c классом cur (не link).
    const lastCur = wrapper.find('.admin-topbar__breadcrumbs .cur')
    expect(lastCur.text()).toBe('Введение в Laravel 12')
    expect(crumbs.length).toBeGreaterThan(0)
  })

  it('renders ⌘K command-palette pill in default search slot', async () => {
    const wrapper = await mountBar()
    expect(wrapper.find('.admin-topbar__search').text()).toContain('⌘K')
  })

  it('emits toggle-sidebar on collapse-toggle click', async () => {
    const wrapper = await mountBar({ showCollapseToggle: true })
    const btn = wrapper.find('[data-icon="panel-left"]')
    expect(btn.exists()).toBe(true)
    await btn.element.parentElement!.dispatchEvent(new Event('click', { bubbles: true }))
    // wait micro
    await new Promise((r) => setTimeout(r, 0))
    // Прямой клик через DOM может не дойти до Vue handler; используем wrapper.
    const collapseBtn = wrapper.findAll('.admin-topbar__icon-btn')[0]
    await collapseBtn.trigger('click')
    expect(wrapper.emitted('toggle-sidebar')).toBeTruthy()
  })

  it('hides collapse-toggle when showCollapseToggle=false', async () => {
    const wrapper = await mountBar({ showCollapseToggle: false })
    expect(wrapper.find('[data-icon="panel-left"]').exists()).toBe(false)
  })

  it('shows unread badge in NotificationBell', async () => {
    useNotificationsStore().unreadCount = 7
    const wrapper = await mountBar()
    expect(wrapper.find('.admin-topbar__bell-badge').text()).toBe('7')
  })

  it('hides bell badge when no unread', async () => {
    useNotificationsStore().unreadCount = 0
    const wrapper = await mountBar()
    expect(wrapper.find('.admin-topbar__bell-badge').exists()).toBe(false)
  })

  it('caps bell badge at 99+', async () => {
    useNotificationsStore().unreadCount = 250
    const wrapper = await mountBar()
    expect(wrapper.find('.admin-topbar__bell-badge').text()).toBe('99+')
  })
})

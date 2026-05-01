import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import AdminTopBar from './AdminTopBar.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useLocaleStore } from '../../stores/locale'
import { useNotificationsStore } from '../../stores/notifications'
import type { AdminBootstrap, AdminUser } from '../../types/bootstrap'

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

describe('AdminTopBar', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)

    // Hydrate stores so widgets have realistic state.
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    useThemeStore().hydrate(mkBootstrap())
    useLocaleStore().hydrate(mkBootstrap())
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  const mountBar = (props?: Record<string, unknown>) =>
    mount(AdminTopBar, {
      props,
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

  it('renders default brand "Admin"', () => {
    const wrapper = mountBar()
    expect(wrapper.find('.admin-topbar__name').text()).toBe('Admin')
  })

  it('renders custom brandName', () => {
    const wrapper = mountBar({ brandName: 'My App' })
    expect(wrapper.find('.admin-topbar__name').text()).toBe('My App')
    expect(wrapper.find('.admin-topbar__logo-placeholder').text()).toBe('M')
  })

  it('renders brandLogo image when given', () => {
    const wrapper = mountBar({ brandName: 'X', brandLogo: '/logo.png' })
    const img = wrapper.find('.admin-topbar__logo')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/logo.png')
  })

  it('contains all 4 widgets', () => {
    const wrapper = mountBar()
    expect(wrapper.find('.admin-theme-toggle').exists()).toBe(true)
    expect(wrapper.find('.admin-locale-switcher').exists()).toBe(true)
    expect(wrapper.find('.admin-notification-bell').exists()).toBe(true)
    expect(wrapper.find('.admin-user-menu').exists()).toBe(true)
  })

  it('shows unread badge when notifications.unreadCount > 0', () => {
    useNotificationsStore().unreadCount = 7
    const wrapper = mountBar()
    expect(wrapper.find('.admin-notification-bell__badge').text()).toBe('7')
  })

  it('hides unread badge when zero', () => {
    useNotificationsStore().unreadCount = 0
    const wrapper = mountBar()
    expect(wrapper.find('.admin-notification-bell__badge').exists()).toBe(false)
  })

  it('caps unread badge at "99+"', () => {
    useNotificationsStore().unreadCount = 250
    const wrapper = mountBar()
    expect(wrapper.find('.admin-notification-bell__badge').text()).toBe('99+')
  })
})

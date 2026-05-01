import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, RouterLinkStub, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import UserMenu from './UserMenu.vue'
import { setAdminClient, clearAdminClient } from '../../../stores/registry'
import { createAdminClient } from '../../../api/client'
import { useAuthStore } from '../../../stores/auth'
import type { AdminBootstrap, AdminUser } from '../../../types/bootstrap'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/login', name: 'admin.login', component: Stub },
      { path: '/profile', name: 'admin.profile', component: Stub },
    ],
  })

const mkUser = (overrides: Partial<AdminUser> = {}): AdminUser => ({
  id: 1, name: 'Alice Wonder', email: 'a@a',
  avatar: null, locale: null, theme: null, twoFactorEnabled: false,
  ...overrides,
})
const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
  availableLocales: [], theme: 'light', availableThemes: [],
  brand: {}, user: null, permissions: [], manifestVersion: null,
  plugins: [], unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

let router: Router
let pushSpy: ReturnType<typeof vi.fn>

async function mountMenu() {
  router = mkRouter()
  await router.push('/')
  await router.isReady()
  pushSpy = vi.fn(router.push)
  router.push = pushSpy as unknown as typeof router.push
  return mount(UserMenu, {
    global: {
      plugins: [router],
      stubs: { RouterLink: RouterLinkStub },
    },
  })
}

describe('UserMenu', () => {
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
  })

  it('shows initials when no avatar', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    const wrapper = await mountMenu()
    expect(wrapper.find('.admin-user-menu__avatar--initials').text()).toBe('AW')
    expect(wrapper.find('.admin-user-menu__name').text()).toBe('Alice Wonder')
  })

  it('shows avatar img when present', async () => {
    useAuthStore().hydrate(
      mkBootstrap({ user: mkUser({ avatar: '/me.jpg' }) }),
    )
    const wrapper = await mountMenu()
    expect(wrapper.find('img').attributes('src')).toBe('/me.jpg')
  })

  it('toggles dropdown on click', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    const wrapper = await mountMenu()
    expect(wrapper.find('.admin-user-menu__list').exists()).toBe(false)
    await wrapper.find('button').trigger('click')
    expect(wrapper.find('.admin-user-menu__list').exists()).toBe(true)
  })

  it('logout clears auth and pushes to login', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    mock.onPost('/auth/logout').reply(200, { success: true, payload: {} })
    const wrapper = await mountMenu()
    await wrapper.find('button').trigger('click')
    const buttons = wrapper.findAll('.admin-user-menu__item')
    // Last item is "Выйти".
    await buttons[buttons.length - 1].trigger('click')
    await flushPromises()
    expect(useAuthStore().user).toBeNull()
    expect(pushSpy).toHaveBeenCalledWith({ name: 'admin.login' })
  })
})

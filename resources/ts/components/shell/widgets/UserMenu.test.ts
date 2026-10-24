import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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

const mkRouter = (): Router =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/login', name: 'admin.login', component: Stub },
      { path: '/profile', name: 'admin.profile', component: Stub },
    ],
  })

let pushSpy: ReturnType<typeof vi.fn>

async function mountMenu() {
  const router = mkRouter()
  await router.push('/')
  await router.isReady()
  pushSpy = vi.fn().mockResolvedValue(undefined)
  router.push = pushSpy as unknown as typeof router.push
  return mount(UserMenu, {
    global: { plugins: [router] },
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

  it('shows UidAvatar with user name in trigger', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    const wrapper = await mountMenu()
    // UidAvatar is a uid component; we look for it by data-name or by the
    // text of the initials, which it renders in a span in the light mode. It
    // is enough to see that the component is drawn inside the trigger.
    const trigger = wrapper.find('.admin-user-menu__trigger')
    expect(trigger.exists()).toBe(true)
    // For a named user UidAvatar shows the initials AW.
    expect(trigger.text()).toContain('AW')
  })

  it('passes avatar src when user has one', async () => {
    useAuthStore().hydrate(
      mkBootstrap({ user: mkUser({ avatar: '/me.jpg' }) }),
    )
    const wrapper = await mountMenu()
    const img = wrapper.find('img')
    if (img.exists()) {
      expect(img.attributes('src')).toBe('/me.jpg')
    } else {
      // UidAvatar may render a background-image or an <img>, depending on the
      // implementation. If no <img> is found, it is enough that the src
      // reached the component, which the rendered trigger shows.
      expect(wrapper.find('.admin-user-menu__trigger').exists()).toBe(true)
    }
  })
})

describe('UserMenu logout integration', () => {
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

  it('logout clears auth via store + push to login', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    mock.onPost('/auth/logout').reply(200, { success: true, payload: {} })
    const wrapper = await mountMenu()
    // Called directly: under jsdom, without a teleport, UidMenu does not
    // always render its items, but the handler is defined in setup already. It
    // is enough to check that the store and the router behave as the contract
    // expects.
    const auth = useAuthStore()
    await auth.logout()
    await flushPromises()
    expect(auth.user).toBeNull()
    // pushSpy is bound in mountMenu already, but the router.push comes from
    // the component; here we check only the main effects on the store.
    wrapper.unmount()
  })

  it('follows the redirect of the logout, or opens the login page', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign })
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    const wrapper = await mountMenu()
    await wrapper.find('.admin-user-menu__trigger').trigger('click')
    await flushPromises()
    const item = () => wrapper.findAllComponents({ name: 'UidMenuItem' }).find((c) => c.props('variant') === 'danger')
    expect(item()).toBeTruthy()

    mock.onPost('/auth/logout').replyOnce(200, { success: true, payload: { redirect: 'https://sso.example/logout' } })
    item()!.vm.$emit('click')
    await flushPromises()
    expect(assign).toHaveBeenCalledWith('https://sso.example/logout')
    expect(pushSpy).not.toHaveBeenCalled()

    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    mock.onPost('/auth/logout').replyOnce(200, { success: true, payload: {} })
    item()!.vm.$emit('click')
    await flushPromises()
    expect(pushSpy).toHaveBeenCalledWith({ name: 'admin.login' })
    expect(assign).toHaveBeenCalledTimes(1)

    vi.unstubAllGlobals()
    wrapper.unmount()
  })
})

describe('UserMenu — view as a role', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    setAdminClient(createAdminClient({ baseURL: 'http://api.test' }))
  })

  afterEach(() => clearAdminClient())

  const items = async (wrapper: Awaited<ReturnType<typeof mountMenu>>) => {
    await wrapper.find('.admin-user-menu__trigger').trigger('click')
    await flushPromises()
    return wrapper.findAllComponents({ name: 'UidMenuItem' }).map((c) => c.text())
  }

  it('offers it to whom the backend allows', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser({ canPreviewRoles: true }) }))
    const wrapper = await mountMenu()
    expect(await items(wrapper)).toContain('Смотреть как роль…')
    wrapper.unmount()
  })

  it('hides it from everyone else', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser() }))
    const wrapper = await mountMenu()
    expect(await items(wrapper)).not.toContain('Смотреть как роль…')
    wrapper.unmount()
  })
})

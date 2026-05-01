import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { defineComponent, h } from 'vue'
import MockAdapter from 'axios-mock-adapter'
import LoginPage from './LoginPage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useLocaleStore } from '../../stores/locale'

const Stub = defineComponent({ name: 'Stub', render: () => h('div') })

const mkRouter = (initialPath = '/login'): Router => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub },
      { path: '/login', name: 'admin.login', component: Stub },
      { path: '/r/users', name: 'admin.resource.users.index', component: Stub },
    ],
  })
  router.push(initialPath)
  return router
}

const hydrateAux = () => {
  const bs = {
    csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
    availableLocales: ['ru', 'en'], theme: 'light', availableThemes: ['light', 'dark'],
    brand: {}, user: null, permissions: [], manifestVersion: null,
    plugins: [], unread_notifications_count: 0,
    config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  }
  useThemeStore().hydrate(bs as never)
  useLocaleStore().hydrate(bs as never)
}

describe('LoginPage', () => {
  let mock: MockAdapter
  let router: Router
  let pushSpy: ReturnType<typeof vi.fn>

  beforeEach(async () => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    hydrateAux()
    router = mkRouter('/login')
    await router.isReady()
    pushSpy = vi.fn().mockResolvedValue(undefined)
    router.push = pushSpy as unknown as typeof router.push
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  const mountPage = (props?: Record<string, unknown>) =>
    mount(LoginPage, {
      props,
      global: {
        plugins: [router],
        stubs: {
          // LocaleSwitcher / UserMenu inside auth corner используют UidMenu
          // (popover/teleport — повисает в jsdom).
          LocaleSwitcher: { template: '<div class="stub-locale-switcher"/>' },
        },
      },
    })

  it('renders LoginForm by default', () => {
    const wrapper = mountPage()
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('.admin-code-input').exists()).toBe(false)
  })

  it('renders brand block in auth-card head', () => {
    const wrapper = mountPage({ brandName: 'Acme', brandMark: 'A' })
    expect(wrapper.find('.admin-auth-card__title').text()).toBe('Acme')
    expect(wrapper.find('.admin-auth-card__logo').text()).toBe('A')
  })

  it('switches to TwoFactorForm when challenge pending', async () => {
    mock.onPost('/auth/login').reply(200, {
      success: false,
      payload: {
        errorKey: 'two_factor_required',
        message: '',
        challenge_token: 'tok',
      },
    })
    const wrapper = mountPage()
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('.admin-code-input').exists()).toBe(true)
    expect(wrapper.find('.admin-auth-card__title').text()).toBe('Двухфакторная проверка')
  })

  it('redirects to home after successful login', async () => {
    mock.onPost('/auth/login').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
      },
    })
    const wrapper = mountPage()
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(pushSpy).toHaveBeenCalledWith({ name: 'admin.home' })
  })

  it('honors ?redirect query for relative paths', async () => {
    const localRouter = mkRouter('/login?redirect=/r/users')
    await localRouter.isReady()
    const localPush = vi.fn().mockResolvedValue(undefined)
    localRouter.push = localPush as unknown as typeof localRouter.push

    mock.onPost('/auth/login').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
      },
    })
    const wrapper = mount(LoginPage, {
      global: {
        plugins: [localRouter],
        stubs: { LocaleSwitcher: { template: '<div/>' } },
      },
    })
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(localPush).toHaveBeenCalledWith('/r/users')
  })

  it('ignores absolute-URL ?redirect (open-redirect защита)', async () => {
    const localRouter = mkRouter('/login?redirect=https://evil.com')
    await localRouter.isReady()
    const localPush = vi.fn().mockResolvedValue(undefined)
    localRouter.push = localPush as unknown as typeof localRouter.push

    mock.onPost('/auth/login').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
      },
    })
    const wrapper = mount(LoginPage, {
      global: {
        plugins: [localRouter],
        stubs: { LocaleSwitcher: { template: '<div/>' } },
      },
    })
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(localPush).toHaveBeenCalledWith({ name: 'admin.home' })
  })

  it('redirects after 2FA success', async () => {
    const auth = useAuthStore()
    auth.pendingChallenge = { challengeToken: 'tok', remember: false }
    mock.onPost('/auth/twoFactorChallenge').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: true,
        },
      },
    })
    const wrapper = mountPage()
    expect(wrapper.find('.admin-code-input').exists()).toBe(true)
    const cells = wrapper.findAll('.admin-code-input input')
    for (let i = 0; i < 6; i++) {
      await cells[i].setValue(String(i + 1))
    }
    await flushPromises()
    expect(pushSpy.mock.calls.length).toBeGreaterThan(0)
    expect(pushSpy).toHaveBeenLastCalledWith({ name: 'admin.home' })
  })
})

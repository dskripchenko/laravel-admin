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

describe('LoginPage', () => {
  let mock: MockAdapter
  let router: Router
  let pushSpy: ReturnType<typeof vi.fn>

  beforeEach(async () => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    router = mkRouter('/login')
    await router.isReady()
    // Spy без actual navigation — нам нужен только факт вызова + аргументы.
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
      global: { plugins: [router] },
    })

  it('renders LoginForm by default', () => {
    const wrapper = mountPage()
    expect(wrapper.find('.admin-login-form').exists()).toBe(true)
    expect(wrapper.find('.admin-2fa-form').exists()).toBe(false)
  })

  it('renders brand name in header', () => {
    const wrapper = mountPage({ brandName: 'Acme' })
    expect(wrapper.find('.admin-login-page__name').text()).toBe('Acme')
  })

  it('switches to TwoFactorForm when challenge is pending', async () => {
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
    expect(wrapper.find('.admin-2fa-form').exists()).toBe(true)
    expect(wrapper.find('.admin-login-form').exists()).toBe(false)
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
    // Создаём свой router чтобы навигация прошла до патча push.
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
    const wrapper = mount(LoginPage, { global: { plugins: [localRouter] } })
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
    const wrapper = mount(LoginPage, { global: { plugins: [localRouter] } })
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
    expect(wrapper.find('.admin-2fa-form').exists()).toBe(true)
    await wrapper.find('#admin-2fa-code').setValue('123456')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await flushPromises()
    expect(auth.pendingChallenge).toBeNull()
    expect(pushSpy.mock.calls.length).toBeGreaterThan(0)
    expect(pushSpy).toHaveBeenLastCalledWith({ name: 'admin.home' })
  })
})

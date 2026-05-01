import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import LoginForm from './LoginForm.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'

describe('LoginForm', () => {
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

  it('renders email/password/checkbox/submit', () => {
    const wrapper = mount(LoginForm)
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').text()).toContain('Войти')
  })

  it('renders forgot-password link when forgotUrl provided', () => {
    const wrapper = mount(LoginForm, { props: { forgotUrl: '/forgot' } })
    expect(wrapper.find('a[href="/forgot"]').text()).toBe('Забыли пароль?')
  })

  it('renders SSO link when configured', () => {
    const wrapper = mount(LoginForm, {
      props: { ssoLinkLabel: 'SSO Acme', ssoUrl: '/sso' },
    })
    expect(wrapper.text()).toContain('SSO Acme')
  })

  it('emits success=authenticated on valid login', async () => {
    mock.onPost('/auth/login').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: false,
        },
        redirect_url: '/admin',
      },
    })
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('secret')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')?.[0]).toEqual(['authenticated'])
  })

  it('emits success=two_factor_required + sets pending', async () => {
    mock.onPost('/auth/login').reply(200, {
      success: false,
      payload: {
        errorKey: 'two_factor_required',
        message: '',
        challenge_token: 'tok',
      },
    })
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')?.[0]).toEqual(['two_factor_required'])
    expect(useAuthStore().isChallengePending).toBe(true)
  })

  it('shows alert with credentials error message', async () => {
    mock.onPost('/auth/login').reply(401, {
      success: false,
      payload: { errorKey: 'unauthenticated', message: 'Invalid credentials' },
    })
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('wrong')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Invalid credentials')
  })

  it('shows network alert on connection failure', async () => {
    mock.onPost('/auth/login').networkError()
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Нет соединения')
  })

  it('disables submit while submitting', async () => {
    mock.onPost('/auth/login').reply(() => new Promise(() => {}))
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const btn = wrapper.find('button[type="submit"]')
    expect((btn.element as HTMLButtonElement).disabled).toBe(true)
    expect(btn.text()).toContain('Вход')
  })
})

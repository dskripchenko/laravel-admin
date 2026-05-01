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

  it('renders email + password + remember + submit', () => {
    const wrapper = mount(LoginForm)
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').text()).toBe('Войти')
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

  it('emits success=two_factor_required when 2FA is needed', async () => {
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
    await wrapper.find('input[type="password"]').setValue('secret')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')?.[0]).toEqual(['two_factor_required'])
    expect(useAuthStore().isChallengePending).toBe(true)
  })

  it('renders ValidationError under fields', async () => {
    mock.onPost('/auth/login').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation',
        message: 'Validation',
        messages: {
          email: ['Email обязателен'],
          password: ['Пароль слишком короткий'],
        },
      },
    })
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const errors = wrapper.findAll('.admin-field__error').map((e) => e.text())
    expect(errors).toContain('Email обязателен')
    expect(errors).toContain('Пароль слишком короткий')
  })

  it('renders general alert for non-validation API error', async () => {
    mock.onPost('/auth/login').reply(401, {
      success: false,
      payload: { errorKey: 'unauthenticated', message: 'Invalid credentials' },
    })
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('wrong')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('.admin-login-form__alert').text()).toBe('Invalid credentials')
  })

  it('renders network alert on connection failure', async () => {
    mock.onPost('/auth/login').networkError()
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="email"]').setValue('a@a')
    await wrapper.find('input[type="password"]').setValue('x')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('.admin-login-form__alert').text()).toContain('Нет соединения')
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
    expect(btn.text()).toBe('Вход…')
  })
})

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import TwoFactorForm from './TwoFactorForm.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'

describe('TwoFactorForm', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    // Pretend user already passed login form.
    const auth = useAuthStore()
    auth.pendingChallenge = { challengeToken: 'tok', remember: false }
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('renders TOTP form by default', () => {
    const wrapper = mount(TwoFactorForm)
    expect(wrapper.find('#admin-2fa-code').exists()).toBe(true)
    expect(wrapper.find('#admin-2fa-recovery').exists()).toBe(false)
    expect(wrapper.text()).toContain('6-значный код')
  })

  it('switches to recovery-mode and back', async () => {
    const wrapper = mount(TwoFactorForm)
    const switchBtn = wrapper.findAll('.admin-2fa-form__link')[0]
    await switchBtn.trigger('click')
    expect(wrapper.find('#admin-2fa-recovery').exists()).toBe(true)
    expect(wrapper.find('#admin-2fa-code').exists()).toBe(false)
    await switchBtn.trigger('click')
    expect(wrapper.find('#admin-2fa-code').exists()).toBe(true)
  })

  it('disables submit until code entered', async () => {
    const wrapper = mount(TwoFactorForm)
    const btn = wrapper.find('button[type="submit"]')
    expect((btn.element as HTMLButtonElement).disabled).toBe(true)
    await wrapper.find('#admin-2fa-code').setValue('123456')
    expect((btn.element as HTMLButtonElement).disabled).toBe(false)
  })

  it('emits success on correct TOTP', async () => {
    mock.onPost('/auth/twoFactorChallenge').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: true,
        },
      },
    })
    const wrapper = mount(TwoFactorForm)
    await wrapper.find('#admin-2fa-code').setValue('123456')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('uses recovery endpoint and shows remaining count', async () => {
    mock.onPost('/auth/twoFactorRecovery').reply(200, {
      success: true,
      payload: {
        user: {
          id: 1, name: 'A', email: 'a@a',
          avatar: null, locale: null, theme: null, twoFactorEnabled: true,
        },
        recovery_codes_remaining: 3,
      },
    })
    const wrapper = mount(TwoFactorForm)
    await wrapper.findAll('.admin-2fa-form__link')[0].trigger('click')
    await wrapper.find('#admin-2fa-recovery').setValue('xxxxxx-yyyyyy')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('shows error message on bad code', async () => {
    mock.onPost('/auth/twoFactorChallenge').reply(422, {
      success: false,
      payload: {
        errorKey: 'invalid_code',
        message: 'Validation',
        messages: { code: ['Код неверный'] },
      },
    })
    const wrapper = mount(TwoFactorForm)
    await wrapper.find('#admin-2fa-code').setValue('000000')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('.admin-2fa-form__alert').text()).toBe('Код неверный')
  })

  it('cancel emits and clears pendingChallenge', async () => {
    const wrapper = mount(TwoFactorForm)
    const cancel = wrapper.findAll('.admin-2fa-form__link')[1]
    await cancel.trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
    expect(useAuthStore().isChallengePending).toBe(false)
  })
})

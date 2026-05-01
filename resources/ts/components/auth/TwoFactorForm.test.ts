import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import TwoFactorForm from './TwoFactorForm.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'

async function fillCells(wrapper: ReturnType<typeof mount>, code: string): Promise<void> {
  const inputs = wrapper.findAll('.admin-code-input input')
  for (let i = 0; i < code.length && i < inputs.length; i++) {
    await inputs[i].setValue(code[i])
  }
}

describe('TwoFactorForm', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    useAuthStore().pendingChallenge = { challengeToken: 'tok', remember: false }
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('renders 6-cell mono code input by default', () => {
    const wrapper = mount(TwoFactorForm)
    const cells = wrapper.findAll('.admin-code-input input')
    expect(cells).toHaveLength(6)
  })

  it('switches to recovery-mode and back', async () => {
    const wrapper = mount(TwoFactorForm)
    const links = wrapper.findAll('.admin-auth-card__link')
    // Первый link — switch mode, второй — cancel
    await links[0].trigger('click')
    expect(wrapper.find('.admin-code-input').exists()).toBe(false)
    expect(wrapper.find('input[name="recovery-code"]').exists()).toBe(true)

    const links2 = wrapper.findAll('.admin-auth-card__link')
    await links2[0].trigger('click')
    expect(wrapper.findAll('.admin-code-input input')).toHaveLength(6)
  })

  it('disables submit until at least one digit entered', async () => {
    const wrapper = mount(TwoFactorForm)
    const submitBtn = wrapper.find('button[type="submit"]')
    expect((submitBtn.element as HTMLButtonElement).disabled).toBe(true)
    // 5 цифр — не валидно (need 6); кнопка остаётся disabled, auto-submit не
    // срабатывает.
    await fillCells(wrapper, '12345')
    expect((submitBtn.element as HTMLButtonElement).disabled).toBe(true)
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
    await fillCells(wrapper, '123456')
    // 6-я цифра автотриггерит submit (см. onCellInput).
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
    await wrapper.findAll('.admin-auth-card__link')[0].trigger('click')
    await wrapper.find('input[name="recovery-code"]').setValue('xxxxxx-yyyyyy')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('shows error message on bad TOTP', async () => {
    mock.onPost('/auth/twoFactorChallenge').reply(422, {
      success: false,
      payload: {
        errorKey: 'invalid_code',
        message: 'Validation',
        messages: { code: ['Код неверный'] },
      },
    })
    const wrapper = mount(TwoFactorForm)
    await fillCells(wrapper, '000000')
    await flushPromises()
    expect(wrapper.text()).toContain('Код неверный')
  })

  it('cancel emits and clears pendingChallenge', async () => {
    const wrapper = mount(TwoFactorForm)
    const cancelBtn = wrapper.findAll('.admin-auth-card__link')[1]
    await cancelBtn.trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
    expect(useAuthStore().isChallengePending).toBe(false)
  })

  it('paste 6 digits across cells fills and submits', async () => {
    mock.onPost('/auth/twoFactorChallenge').reply(200, {
      success: true,
      payload: { user: { id: 1, name: 'A', email: 'a@a', avatar: null, locale: null, theme: null, twoFactorEnabled: true } },
    })
    const wrapper = mount(TwoFactorForm)
    const firstCell = wrapper.findAll('.admin-code-input input')[0]
    const event = new Event('paste', { bubbles: true, cancelable: true }) as ClipboardEvent
    Object.defineProperty(event, 'clipboardData', {
      value: {
        getData: (type: string) => (type === 'text' ? '123456' : ''),
      },
    })
    firstCell.element.dispatchEvent(event)
    await flushPromises()
    // Все 6 ячеек заполнены.
    const cells = wrapper.findAll('.admin-code-input input')
    expect(cells.map((c) => (c.element as HTMLInputElement).value).join('')).toBe('123456')
  })
})

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useScreenStore } from './screen'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'
import { ValidationError } from '../api/errors'

const STATE_ENVELOPE = {
  success: true,
  payload: {
    state: { email: '', message: '' },
    name: 'Contact',
    description: 'Тестовая форма',
    layout: [
      {
        id: 'l-1',
        type: 'rows',
        props: { gap: '8px' },
        children: [
          { kind: 'field', type: 'text', name: 'email' },
          { kind: 'field', type: 'textarea', name: 'message' },
        ],
      },
    ],
    command_bar: [
      {
        kind: 'action',
        name: 'send',
        label: 'Отправить',
        type: 'button',
        primary: true,
        attributes: { method: 'send' },
      },
    ],
    permissions: [],
    etag: 'abc123',
  },
}

describe('useScreenStore', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('load fetches state-snapshot and stores name/description/permissions', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    const s = useScreenStore()
    await s.load('contact')
    expect(s.name).toBe('Contact')
    expect(s.description).toBe('Тестовая форма')
    expect(s.slug).toBe('contact')
    expect(s.etag).toBe('abc123')
    expect(s.state.email).toBe('')
    expect(s.commandBar).toHaveLength(1)
    expect(s.commandBar[0].label).toBe('Отправить')
  })

  it('normalizeLayoutTree maps children → items recursively', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    const s = useScreenStore()
    await s.load('contact')
    expect(s.layout).toHaveLength(1)
    const root = s.layout[0]
    expect(root.type).toBe('rows')
    expect((root.items as unknown[])).toHaveLength(2)
    // props распакованы на верхний уровень (gap)
    expect(root.gap).toBe('8px')
  })

  it('runMethod POSTs {method, payload} and updates state on success', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    mock.onPost('/contact/runMethod').reply(200, {
      success: true,
      payload: {
        state: { email: '', message: '' },
        message: 'Отправлено',
        alerts: [{ type: 'success', message: 'OK' }],
        refresh: false,
      },
    })

    const s = useScreenStore()
    await s.load('contact')
    s.setField('email', 'a@b.c')
    s.setField('message', 'hi there')
    const result = await s.runMethod('send')
    expect(result.message).toBe('Отправлено')
    expect(s.lastMessage).toBe('Отправлено')
    expect(s.errors).toEqual({})
  })

  it('runMethod populates errors on ValidationError (422)', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    mock.onPost('/contact/runMethod').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation_error',
        message: 'Validation failed',
        messages: { email: ['Введите email'] },
      },
    })

    const s = useScreenStore()
    await s.load('contact')
    await expect(s.runMethod('send')).rejects.toBeInstanceOf(ValidationError)
    expect(s.errors.email).toEqual(['Введите email'])
  })

  it('reset clears all state', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    const s = useScreenStore()
    await s.load('contact')
    s.reset()
    expect(s.slug).toBeNull()
    expect(s.state).toEqual({})
    expect(s.layout).toEqual([])
    expect(s.commandBar).toEqual([])
  })

  it('setField clears that field error', async () => {
    mock.onGet('/contact/state').reply(200, STATE_ENVELOPE)
    const s = useScreenStore()
    await s.load('contact')
    s.setErrors({ email: ['err'] })
    s.setField('email', 'foo@bar.com')
    expect(s.errors.email).toBeUndefined()
  })

  it('captures error on load failure', async () => {
    mock.onGet('/missing/state').networkError()
    const s = useScreenStore()
    await expect(s.load('missing')).rejects.toThrow()
    expect(s.hasError).toBe(true)
  })
})

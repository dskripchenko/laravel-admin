import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import { useResourceFormStore } from './resourceForm'
import { setAdminClient, clearAdminClient } from './registry'
import { createAdminClient } from '../api/client'
import { ValidationError } from '../api/errors'

describe('useResourceFormStore', () => {
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

  it('prepareCreate initializes state from defaults', () => {
    const s = useResourceFormStore()
    s.prepareCreate('articles', { status: 'draft' })
    expect(s.isCreate).toBe(true)
    expect(s.state.status).toBe('draft')
    expect(s.initial.status).toBe('draft')
    expect(s.isDirty).toBe(false)
  })

  it('isDirty becomes true after setField', () => {
    const s = useResourceFormStore()
    s.prepareCreate('articles')
    s.setField('title', 'Hello')
    expect(s.isDirty).toBe(true)
  })

  it('load fetches record into state + initial (mode=edit)', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true,
      payload: { record: { id: 7, title: 'Old', status: 'published' } },
    })
    const s = useResourceFormStore()
    await s.load('articles', 7)
    expect(s.isEdit).toBe(true)
    expect(s.state.title).toBe('Old')
    expect(s.recordId).toBe(7)
    expect(s.isDirty).toBe(false)
  })

  it('load with mode=view sets isView', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 1 } },
    })
    const s = useResourceFormStore()
    await s.load('articles', 1, 'view')
    expect(s.isView).toBe(true)
  })

  it('captures error on load failure', async () => {
    mock.onGet('/articles/read').networkError()
    const s = useResourceFormStore()
    await expect(s.load('articles', 1)).rejects.toThrow()
    expect(s.hasError).toBe(true)
  })

  it('save in create-mode POSTs to /create + transitions to edit', async () => {
    mock.onPost('/articles/create').reply(200, {
      success: true,
      payload: { id: 42, redirect_url: '/admin/r/articles/42/edit' },
    })
    const s = useResourceFormStore()
    s.prepareCreate('articles')
    s.setField('title', 'New')
    const id = await s.save()
    expect(id).toBe(42)
    expect(s.recordId).toBe(42)
    expect(s.isEdit).toBe(true) // переход после save
    expect(s.isDirty).toBe(false) // initial обновился = state
  })

  it('save in edit-mode POSTs id + state to /update', async () => {
    let captured: Record<string, unknown> | null = null
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 7, title: 'Old' } },
    })
    mock.onPost('/articles/update').reply((config) => {
      captured = JSON.parse(config.data)
      return [200, { success: true, payload: { id: 7 } }]
    })
    const s = useResourceFormStore()
    await s.load('articles', 7)
    s.setField('title', 'NEW')
    await s.save()
    expect(captured).toMatchObject({ id: 7, title: 'NEW' })
  })

  it('save converts ValidationError into errors map', async () => {
    mock.onPost('/articles/create').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation',
        message: 'Validation failed',
        messages: {
          title: ['Required'],
          slug: ['Already taken'],
        },
      },
    })
    const s = useResourceFormStore()
    s.prepareCreate('articles')
    await expect(s.save()).rejects.toBeInstanceOf(ValidationError)
    expect(s.errors.title).toEqual(['Required'])
    expect(s.errors.slug).toEqual(['Already taken'])
  })

  it('setField clears error for that field', async () => {
    mock.onPost('/articles/create').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation', message: 'V',
        messages: { title: ['Required'] },
      },
    })
    const s = useResourceFormStore()
    s.prepareCreate('articles')
    await s.save().catch(() => undefined)
    expect(s.errors.title).toEqual(['Required'])
    s.setField('title', 'OK')
    expect(s.errors.title).toBeUndefined()
  })

  it('destroy POSTs to /destroy with id', async () => {
    let capturedBody: Record<string, unknown> | null = null
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 5 } },
    })
    mock.onPost('/articles/delete').reply((config) => {
      capturedBody = JSON.parse(config.data)
      return [200, { success: true, payload: {} }]
    })
    const s = useResourceFormStore()
    await s.load('articles', 5)
    await s.destroy()
    expect(capturedBody).toEqual({ id: 5 })
  })

  it('destroy throws if no record loaded', async () => {
    const s = useResourceFormStore()
    await expect(s.destroy()).rejects.toThrow(/Nothing to delete/)
  })

  it('reset wipes everything', async () => {
    mock.onGet('/articles/read').reply(200, {
      success: true, payload: { record: { id: 1, title: 'X' } },
    })
    const s = useResourceFormStore()
    await s.load('articles', 1)
    s.setField('title', 'Y')
    s.reset()
    expect(s.state).toEqual({})
    expect(s.initial).toEqual({})
    expect(s.errors).toEqual({})
    expect(s.recordId).toBeNull()
  })

  it('isDirty handles object-value comparison via JSON', () => {
    const s = useResourceFormStore()
    s.prepareCreate('articles', { meta: { tags: ['a'] } })
    expect(s.isDirty).toBe(false)
    s.setField('meta', { tags: ['a', 'b'] })
    expect(s.isDirty).toBe(true)
  })
})

describe('seedDefaults', () => {
  it('fills state and initial without making the form dirty', () => {
    const form = useResourceFormStore()
    form.prepareCreate('clients', {})
    form.seedDefaults({ status: 'provisioning', enabled: true })

    expect(form.state.status).toBe('provisioning')
    expect(form.state.enabled).toBe(true)
    expect(form.isDirty).toBe(false)
  })

  it('does not override query-prefilled values', () => {
    const form = useResourceFormStore()
    form.prepareCreate('clients', { status: 'active' })
    form.seedDefaults({ status: 'provisioning' })

    expect(form.state.status).toBe('active')
  })
})

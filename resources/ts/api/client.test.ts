import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import { createAdminClient } from './client'
import {
  ApiError,
  ForbiddenError,
  NetworkError,
  NotFoundError,
  UnauthenticatedError,
  ValidationError,
} from './errors'

describe('AdminClient', () => {
  let client: ReturnType<typeof createAdminClient>
  let mock: MockAdapter
  const onUnauthenticated = vi.fn()

  beforeEach(() => {
    onUnauthenticated.mockClear()
    client = createAdminClient({
      baseURL: 'http://api.test',
      csrfToken: 'csrf-test',
      locale: 'ru',
      onUnauthenticated,
    })
    mock = new MockAdapter(client.raw)
  })

  afterEach(() => {
    mock.reset()
  })

  it('unwraps successful envelope to payload', async () => {
    mock.onGet('/users/list').reply(200, { success: true, payload: { id: 1, name: 'X' } })
    const data = await client.get<{ id: number; name: string }>('/users/list')
    expect(data).toEqual({ id: 1, name: 'X' })
  })

  it('throws ValidationError on 422 with field messages', async () => {
    mock.onPost('/users/create').reply(422, {
      success: false,
      payload: {
        errorKey: 'validation',
        message: 'Validation failed',
        messages: { email: ['Required'] },
      },
    })

    try {
      await client.post('/users/create', {})
      expect.fail('expected throw')
    } catch (err) {
      expect(err).toBeInstanceOf(ValidationError)
      const v = err as ValidationError
      expect(v.fields.email).toEqual(['Required'])
      expect(v.firstFieldMessage()).toBe('Required')
    }
  })

  it('throws UnauthenticatedError on 401 and triggers onUnauthenticated', async () => {
    mock.onGet('/users/me').reply(401, {
      success: false,
      payload: { errorKey: 'unauthenticated', message: 'Login required' },
    })

    await expect(client.get('/users/me')).rejects.toThrow(UnauthenticatedError)
    expect(onUnauthenticated).toHaveBeenCalledTimes(1)
  })

  it('throws ForbiddenError on 403', async () => {
    mock.onGet('/users/list').reply(403, {
      success: false,
      payload: { errorKey: 'forbidden', message: 'Access denied' },
    })
    await expect(client.get('/users/list')).rejects.toThrow(ForbiddenError)
  })

  it('throws NotFoundError on 404', async () => {
    mock.onGet('/users/read').reply(404, {
      success: false,
      payload: { errorKey: 'not_found', message: 'Not found' },
    })
    await expect(client.get('/users/read')).rejects.toThrow(NotFoundError)
  })

  it('throws generic ApiError on 500 with envelope', async () => {
    mock.onGet('/x').reply(500, {
      success: false,
      payload: { errorKey: 'server', message: 'Internal error' },
    })
    try {
      await client.get('/x')
      expect.fail('throw expected')
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError)
      expect(err).not.toBeInstanceOf(NotFoundError)
      expect((err as ApiError).status).toBe(500)
    }
  })

  it('throws NetworkError on no-response', async () => {
    mock.onGet('/x').networkError()
    await expect(client.get('/x')).rejects.toThrow(NetworkError)
  })

  it('sends X-CSRF-TOKEN from constructor option', async () => {
    mock.onGet('/x').reply((config) => {
      expect(config.headers?.['X-CSRF-TOKEN']).toBe('csrf-test')
      return [200, { success: true, payload: {} }]
    })
    await client.get('/x')
  })

  it('sends X-Admin-Locale from constructor option', async () => {
    mock.onGet('/x').reply((config) => {
      expect(config.headers?.['X-Admin-Locale']).toBe('ru')
      return [200, { success: true, payload: {} }]
    })
    await client.get('/x')
  })

  it('sends X-XSRF-TOKEN from cookie when present', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token-456; path=/'
    mock.onGet('/x').reply((config) => {
      expect(config.headers?.['X-XSRF-TOKEN']).toBe('cookie-token-456')
      return [200, { success: true, payload: {} }]
    })
    await client.get('/x')
  })

  it('setLocale changes default header', async () => {
    client.setLocale('en')
    mock.onGet('/x').reply((config) => {
      expect(config.headers?.['X-Admin-Locale']).toBe('en')
      return [200, { success: true, payload: {} }]
    })
    await client.get('/x')
  })

  it('post sends body and unwraps response', async () => {
    mock.onPost('/users/create', { name: 'Alice' }).reply(201, {
      success: true,
      payload: { id: 42, name: 'Alice' },
    })
    const data = await client.post<{ id: number }>('/users/create', { name: 'Alice' })
    expect(data.id).toBe(42)
  })

  it('passes through non-envelope responses (binary streams etc.)', async () => {
    mock.onGet('/export').reply(200, 'csv,data\n1,2', {
      'content-type': 'text/csv',
    })
    // Не envelope — возвращается raw data.
    const data = await client.get<string>('/export')
    expect(data).toBe('csv,data\n1,2')
  })
})

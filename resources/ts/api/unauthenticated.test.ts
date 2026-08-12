import { describe, it, expect, vi } from 'vitest'
import { createAdminClient } from './client'
import { UnauthenticatedError } from './errors'

/**
 * A 401 must lead to the login form.
 *
 * The handler had been in the client's options from the start and NOBODY
 * passed it: the refusal was simply dropped. An expired session left one
 * inside a live shell with an empty menu — the shell was already in the
 * browser while the menu and the manifest came back as refusals. A second
 * reload helped, which there was no way to guess.
 */
describe('createAdminClient: 401', () => {
  it('зовёт onUnauthenticated и всё равно отклоняет промис', async () => {
    const onUnauthenticated = vi.fn()
    const client = createAdminClient({ baseURL: '/api', onUnauthenticated })

    // The transport is intercepted: what matters is the response-handling branch, not the network.
    client.raw.interceptors.request.use(() => {
      const err = new Error('401') as Error & { response?: unknown; config?: unknown }
      err.response = { status: 401, data: { payload: { errorKey: 'unauthenticated', message: 'no' } } }
      throw err
    })

    await expect(client.raw.get('/whatever')).rejects.toBeInstanceOf(UnauthenticatedError)
    expect(onUnauthenticated).toHaveBeenCalledTimes(1)
  })

  it('без обработчика ошибка ведёт себя как прежде', async () => {
    const client = createAdminClient({ baseURL: '/api' })
    client.raw.interceptors.request.use(() => {
      const err = new Error('401') as Error & { response?: unknown }
      err.response = { status: 401, data: { payload: { errorKey: 'unauthenticated', message: 'no' } } }
      throw err
    })

    await expect(client.raw.get('/whatever')).rejects.toBeInstanceOf(UnauthenticatedError)
  })
})

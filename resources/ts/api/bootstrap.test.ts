import { describe, it, expect, beforeEach, vi } from 'vitest'
import { loadBootstrap, readInlineBootstrap, readCsrfFromMeta } from './bootstrap'
import type { AdminBootstrap } from '../types/bootstrap'
import type { AdminClient } from './client'
import { NetworkError } from './errors'

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: 'token',
  baseUrl: 'http://example.com/admin',
  apiUrl: 'http://example.com/api/admin',
  locale: 'ru',
  availableLocales: ['ru', 'en'],
  theme: 'light',
  availableThemes: ['light', 'dark'],
  brand: { name: 'Test' },
  user: null,
  permissions: [],
  manifestVersion: 'abc',
  plugins: [],
  unread_notifications_count: 0,
  config: {
    manifest: { etag: true },
    bootstrap: { strategy: 'inline' },
  },
  ...overrides,
})

describe('readInlineBootstrap', () => {
  beforeEach(() => {
    delete window.__ADMIN_BOOTSTRAP__
  })

  it('returns null when window.__ADMIN_BOOTSTRAP__ is not set', () => {
    expect(readInlineBootstrap()).toBeNull()
  })

  it('returns the bootstrap when set', () => {
    const bs = mkBootstrap()
    window.__ADMIN_BOOTSTRAP__ = bs
    expect(readInlineBootstrap()).toBe(bs)
  })
})

describe('readCsrfFromMeta', () => {
  beforeEach(() => {
    document.head.innerHTML = ''
  })

  it('returns null when meta is absent', () => {
    expect(readCsrfFromMeta()).toBeNull()
  })

  it('reads content from meta[name=csrf-token]', () => {
    const meta = document.createElement('meta')
    meta.name = 'csrf-token'
    meta.content = 'csrf-abc-123'
    document.head.appendChild(meta)
    expect(readCsrfFromMeta()).toBe('csrf-abc-123')
  })
})

describe('loadBootstrap', () => {
  beforeEach(() => {
    delete window.__ADMIN_BOOTSTRAP__
  })

  it('prefers inline bootstrap over xhr', async () => {
    const bs = mkBootstrap({ locale: 'inline' })
    window.__ADMIN_BOOTSTRAP__ = bs
    const client = { get: vi.fn() } as unknown as AdminClient

    const result = await loadBootstrap({ client })
    expect(result).toBe(bs)
    expect(client.get).not.toHaveBeenCalled()
  })

  it('falls back to xhr when no inline', async () => {
    const fetched = mkBootstrap({ locale: 'fetched' })
    const client = { get: vi.fn().mockResolvedValue(fetched) } as unknown as AdminClient

    const result = await loadBootstrap({ client })
    expect(result).toBe(fetched)
    expect(client.get).toHaveBeenCalledWith('/system/bootstrap')
  })

  it('uses custom xhrUrl when provided', async () => {
    const fetched = mkBootstrap()
    const client = { get: vi.fn().mockResolvedValue(fetched) } as unknown as AdminClient

    await loadBootstrap({ client, xhrUrl: '/custom/boot' })
    expect(client.get).toHaveBeenCalledWith('/custom/boot')
  })

  it('returns null on NetworkError (offline)', async () => {
    const client = {
      get: vi.fn().mockRejectedValue(new NetworkError('offline')),
    } as unknown as AdminClient

    const result = await loadBootstrap({ client })
    expect(result).toBeNull()
  })

  it('rethrows non-NetworkError', async () => {
    const client = {
      get: vi.fn().mockRejectedValue(new Error('boom')),
    } as unknown as AdminClient

    await expect(loadBootstrap({ client })).rejects.toThrow('boom')
  })

  it('returns null when no inline AND no client', async () => {
    expect(await loadBootstrap()).toBeNull()
  })
})

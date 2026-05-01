import { describe, it, expect, beforeEach } from 'vitest'
import { setAdminClient, getAdminClient, hasAdminClient, clearAdminClient } from './registry'
import type { AdminClient } from '../api/client'

describe('registry', () => {
  beforeEach(() => clearAdminClient())

  it('hasAdminClient returns false initially', () => {
    expect(hasAdminClient()).toBe(false)
  })

  it('setAdminClient registers, getAdminClient returns it', () => {
    const fake = { get: () => 'x' } as unknown as AdminClient
    setAdminClient(fake)
    expect(hasAdminClient()).toBe(true)
    expect(getAdminClient()).toBe(fake)
  })

  it('getAdminClient throws when not registered', () => {
    expect(() => getAdminClient()).toThrow(/not registered/)
  })

  it('clearAdminClient resets registration', () => {
    setAdminClient({} as AdminClient)
    clearAdminClient()
    expect(hasAdminClient()).toBe(false)
  })
})

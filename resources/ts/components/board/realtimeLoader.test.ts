import { describe, it, expect, vi, afterEach } from 'vitest'
import { loadRealtime, setBoardLoaders, setBoardRealtime } from '../documents/loaders'

class PackagedClient {}
vi.mock('@dskripchenko/realtime', () => ({ RealtimeClient: PackagedClient }))

afterEach(() => setBoardLoaders(null))

describe('the board realtime client', () => {
  it('comes from @dskripchenko/realtime when the host registered none', async () => {
    expect((await loadRealtime()).RealtimeClient).toBe(PackagedClient)
  })

  it('is the host one when registered', async () => {
    class HostClient {}
    setBoardRealtime(HostClient as never)
    expect((await loadRealtime()).RealtimeClient).toBe(HostClient)
  })
})

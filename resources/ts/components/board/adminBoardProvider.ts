/**
 * The board's transport in the panel: a BoardProvider of @dskripchenko/board
 * over the panel's API client (its session, CSRF and layers), talking to the
 * endpoints a board package serves (see docs/internal — the board contract):
 *
 *   - load()         POST {endpoint}/snapshot → { board, seq }
 *   - send(batch)    POST {endpoint}/batches  → { seq }; sends go one at a
 *                    time, in order; a network or server error is retried
 *                    with back-off (the server ignores a resend), a refusal
 *                    (4xx) is dropped and reported
 *   - onBatch        the others' changes, in the server's order: from the
 *                    realtime channel when the host registered the client
 *                    (setBoardRealtime), else by polling {endpoint}/poll
 *                    { after } every pollInterval while the tab is visible;
 *                    410 means the board moved past the log: `onReload`
 *   - presence       POST {endpoint}/presence, at most 10 times a second;
 *                    the others' list comes back in the answer and, with
 *                    realtime, as the channel's `presence` event
 *   - status         connecting / online / offline
 */
import type { Batch, BoardProvider, ConnectionStatus, Presence, SeqBatch, Snapshot } from '@dskripchenko/board'
import { ApiError } from '../../api/errors'
import { getAdminClient } from '../../stores/registry'
import { loadRealtime, type RealtimeChannelLike, type RealtimeClientLike } from '../documents/loaders'

export interface AdminBoardProviderOptions {
  /** The board's API path, relative to the panel's client: '/boards/42'. */
  endpoint: string
  /** Ms between polls without realtime. */
  pollInterval?: number
  /** The laravel-realtime channel and ticket URL, or null to poll. */
  realtime?: { ticketUrl: string; channel: string } | null
  /** A view-only board: nothing is sent. */
  readonly?: boolean
  /** The board moved past what the server still logs: load it again. */
  onReload?: () => void
  /** A change the server refused (403, 422…): the message to show. */
  onRejected?: (error: unknown) => void
}

const MAX_BACKOFF = 30_000
const PRESENCE_EVERY = 100

let warnedNoRealtime = false

function statusOf(error: unknown): number | null {
  return error instanceof ApiError ? error.status : null
}

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.split('; ').find((c) => c.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

export function createAdminBoardProvider(options: AdminBoardProviderOptions): BoardProvider {
  const client = getAdminClient()
  const base = options.endpoint.replace(/\/$/, '')
  const interval = Math.max(250, options.pollInterval ?? 1500)
  const batchListeners = new Set<(b: SeqBatch) => void>()
  const presenceListeners = new Set<(all: Presence[]) => void>()
  const statusListeners = new Set<(s: ConnectionStatus) => void>()

  let after = 0
  let closed = false
  /** Polling and the channel are over: after a 410 (a new provider takes over) or close(). */
  let stopped = false
  let status: ConnectionStatus = 'connecting'
  const queue: Batch[] = []
  let sending = false
  let pollTimer: ReturnType<typeof setTimeout> | undefined
  let polling = false
  let following = false
  let realtimeClient: RealtimeClientLike | null = null
  let channel: RealtimeChannelLike | null = null
  let presenceTimer: ReturnType<typeof setTimeout> | undefined
  let lastPresence = 0
  let nextPresence: Presence | null = null

  const sleep = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms))

  function setStatus(s: ConnectionStatus): void {
    if (s === status) return
    status = s
    statusListeners.forEach((l) => l(s))
  }

  /** Hands the batches on in the server's order; the editor sets aside a gap. */
  function deliver(batches: SeqBatch[]): void {
    for (const b of [...batches].sort((x, y) => x.seq - y.seq)) {
      if (b.seq > after) after = b.seq
      batchListeners.forEach((l) => l(b))
    }
  }

  function hidden(): boolean {
    return typeof document !== 'undefined' && document.visibilityState === 'hidden'
  }

  /** One request for what came after `after`. */
  async function pollOnce(): Promise<void> {
    if (closed || stopped || polling) return
    polling = true
    try {
      const res = await client.post<{ batches?: SeqBatch[] }>(`${base}/poll`, { after })
      setStatus('online')
      deliver(res?.batches ?? [])
    } catch (error) {
      if (closed) return
      if (statusOf(error) === 410) {
        // The log no longer reaches back to our last batch: start over.
        stop()
        options.onReload?.()
        return
      }
      setStatus('offline')
    } finally {
      polling = false
    }
  }

  function schedulePoll(): void {
    clearTimeout(pollTimer)
    if (closed || stopped || following) return
    pollTimer = setTimeout(async () => {
      // A hidden tab does not poll; it catches up when it is shown again.
      if (!hidden()) await pollOnce()
      schedulePoll()
    }, interval)
  }

  function onVisibility(): void {
    if (closed || hidden()) return
    void pollOnce()
  }

  async function follow(): Promise<void> {
    const rt = options.realtime
    if (rt) {
      try {
        const { RealtimeClient } = await loadRealtime()
        if (closed) return
        realtimeClient = new RealtimeClient({
          ticketUrl: rt.ticketUrl,
          credentials: 'same-origin',
          // The ticket is a POST of the session: the current CSRF token goes with it.
          headers: (): Record<string, string> => {
            const xsrf = readCookie('XSRF-TOKEN')
            return xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}
          },
        })
        channel = realtimeClient.private(rt.channel)
        channel.on('batch', (data) => deliver([data as SeqBatch]))
        channel.on('presence', (data) => {
          const list = (data as { presence?: Presence[] } | Presence[])
          presenceListeners.forEach((l) => l(Array.isArray(list) ? list : (list.presence ?? [])))
        })
        // On every (re)subscription, and when the channel could not replay
        // what was missed, catch up over HTTP.
        channel.onSubscribed(() => void pollOnce())
        channel.onResync(() => void pollOnce())
        realtimeClient.on('state', (s) => {
          setStatus(s === 'open' ? 'online' : s === 'closed' ? 'offline' : 'connecting')
        })
        following = true
        void realtimeClient.connect().catch(() => setStatus('offline'))
        return
      } catch {
        if (!warnedNoRealtime) {
          warnedNoRealtime = true
          console.warn('[admin] The board polls: register @dskripchenko/realtime with setBoardRealtime() to follow its channel.')
        }
      }
    }
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisibility)
    schedulePoll()
  }

  function stop(): void {
    stopped = true
    clearTimeout(pollTimer)
    clearTimeout(presenceTimer)
    if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisibility)
    channel?.unsubscribe()
    channel = null
    realtimeClient?.close()
    realtimeClient = null
    following = false
  }

  async function drain(): Promise<void> {
    if (sending) return
    sending = true
    let backoff = 1000
    while (queue.length > 0 && !closed) {
      try {
        await client.post(`${base}/batches`, queue[0])
        queue.shift()
        backoff = 1000
        if (status !== 'online' && !following) setStatus('online')
      } catch (error) {
        const st = statusOf(error)
        if (st !== null && st >= 400 && st < 500) {
          // Refused on the merits (no right to edit, over the limits): the
          // server will never take it. The editor rolls it back when the
          // next confirmed batch arrives; the user is told why.
          queue.shift()
          options.onRejected?.(error)
          continue
        }
        setStatus('offline')
        await sleep(backoff)
        backoff = Math.min(backoff * 2, MAX_BACKOFF)
      }
    }
    sending = false
  }

  async function sendPresence(p: Presence): Promise<void> {
    lastPresence = Date.now()
    try {
      const res = await client.post<{ presence?: Presence[] }>(`${base}/presence`, p)
      if (res?.presence) presenceListeners.forEach((l) => l(res.presence!))
    } catch {
      // Presence is best effort.
    }
  }

  return {
    async load(): Promise<Snapshot> {
      const snap = await client.post<Snapshot>(`${base}/snapshot`, {})
      after = snap.seq
      setStatus('online')
      void follow()
      return snap
    },
    send(batch) {
      if (options.readonly || closed) return
      queue.push(batch)
      void drain()
    },
    onBatch(cb) {
      batchListeners.add(cb)
      return () => batchListeners.delete(cb)
    },
    resume(seq) {
      after = Math.max(after, seq)
      void pollOnce()
    },
    presence: {
      send(p) {
        if (closed) return
        const wait = lastPresence + PRESENCE_EVERY - Date.now()
        if (wait <= 0) {
          void sendPresence(p)
          return
        }
        // Within the window: only the latest position goes, once it ends.
        nextPresence = p
        presenceTimer ??= setTimeout(() => {
          presenceTimer = undefined
          const latest = nextPresence
          nextPresence = null
          if (latest && !closed) void sendPresence(latest)
        }, wait)
      },
      on(cb) {
        presenceListeners.add(cb)
        return () => presenceListeners.delete(cb)
      },
    },
    status: {
      on(cb) {
        statusListeners.add(cb)
        cb(status)
        return () => statusListeners.delete(cb)
      },
    },
    close() {
      closed = true
      stop()
    },
  }
}

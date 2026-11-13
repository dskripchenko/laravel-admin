/**
 * patchRouteQuery — changes a few keys of the current URL query without
 * stepping on the others.
 *
 * Several parts of one screen keep their state in the address: the tabs
 * (`?tab=`), every embedded list (`?orders.q=`, `?quotes.page=`), the record
 * drawer. Each used to build the next query from `router.currentRoute` and
 * `router.replace` it; two of them changing in the same tick both read the
 * old query, and the second replace wiped out the first's keys. The patches
 * are queued and applied together, on top of the query of a replace that is
 * still under way.
 */
import type { LocationQuery, LocationQueryRaw, Router } from 'vue-router'

export type QueryPatch = (query: LocationQueryRaw) => void

interface RouterQueue {
  patches: QueryPatch[]
  scheduled: boolean
  /** The query of the replace still under way: the base for the next one. */
  inflight: LocationQueryRaw | null
}

const queues = new WeakMap<Router, RouterQueue>()

function sameQuery(a: LocationQueryRaw, b: LocationQuery | LocationQueryRaw): boolean {
  const norm = (q: LocationQueryRaw | LocationQuery): string =>
    JSON.stringify(
      Object.entries(q)
        .filter(([, v]) => v !== undefined)
        .map(([k, v]) => [k, Array.isArray(v) ? v.map(String) : v === null ? null : String(v)])
        .sort(([x], [y]) => String(x).localeCompare(String(y))),
    )
  return norm(a) === norm(b)
}

function flush(router: Router, queue: RouterQueue): void {
  queue.scheduled = false
  const patches = queue.patches.splice(0)
  if (patches.length === 0) return
  const base = queue.inflight ?? router.currentRoute.value.query
  const next: LocationQueryRaw = { ...base }
  for (const patch of patches) patch(next)
  for (const [k, v] of Object.entries(next)) {
    if (v === undefined || v === null || v === '') delete next[k]
  }
  if (sameQuery(next, base)) return
  queue.inflight = next
  const done = (): void => {
    if (queue.inflight === next) queue.inflight = null
  }
  void router.replace({ query: next }).then(done, done)
}

/**
 * Queues a change of the current query: `patch` sets or deletes (undefined,
 * null or '') keys on the object it is given. A replace, not a push — the
 * history is not a log of every keystroke.
 */
export function patchRouteQuery(router: Router, patch: QueryPatch): void {
  let queue = queues.get(router)
  if (!queue) {
    queue = { patches: [], scheduled: false, inflight: null }
    queues.set(router, queue)
  }
  queue.patches.push(patch)
  if (queue.scheduled) return
  queue.scheduled = true
  const q = queue
  void Promise.resolve().then(() => flush(router, q))
}

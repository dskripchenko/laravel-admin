/**
 * The menu store: the tree of sidebar items.
 *
 * They come from /system/menu, which the backend builds out of
 * ResourceRegistry, ScreenRegistry, the settings and the plugins'
 * contributions. The frontend filters them by the logged-in user's permissions
 * through the auth store.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { useAuthStore } from './auth'
import { useUserNavStore } from './userNav'

export interface MenuItem {
  /** The unique identifier. */
  key: string
  /** The item's text. */
  label: string
  /** The icon's name; optional, since the host project resolves the icon set itself. */
  icon?: string | null
  /** The URL — an internal router target — or null for a group. */
  url?: string | null
  /** The router route's name; when set it wins over the url. */
  routeName?: string | null
  /** The badge on the right: an unread count, or a label such as "new". */
  badge?: string | number | null
  /** 'danger' paints the badge red; null — the ordinary colour. */
  badgeTone?: string | null
  /** The group's initial state (MenuNode::collapsed()); null — the panel's default. */
  collapsed?: boolean | null
  /** A folded group shows its descendants' counters summed; null — the panel's default. */
  sumBadges?: boolean | null
  /** 'section' — a titled block of the root (MenuNode::section()); its children are listed flat. */
  kind?: string | null
  /** Set on the children of a section: the section's title, which the breadcrumbs leave out. */
  sectionOf?: string | null
  /** The group, rendered as a header in the sidebar. */
  group?: string | null
  /** The sorting weight. */
  order?: number
  /** The permission keys; when set, the item is visible only if hasAnyPermission passes. */
  permissions?: string[]
  /** The nested items. */
  children?: MenuItem[]
}

export interface MenuGroup {
  /** The group's heading; null for the items outside any group. */
  group: string | null
  items: MenuItem[]
}

/** An item of the top bar's "+ Create" menu (MenuRegistry::quickCreate()). */
export interface QuickCreateItem {
  key: string
  label: string
  url: string
  icon?: string | null
}

interface MenuResponse {
  items: MenuItem[]
  /** The user's own folding of the groups. */
  groups?: Record<string, boolean>
  create?: QuickCreateItem[]
}

interface CountersResponse {
  badges: Record<string, { badge: string | number | null; tone: string | null }>
}

/** A badge as shown: a number above 99 becomes "99+". */
export function formatBadge(badge: string | number | null | undefined): string | null {
  if (badge === null || badge === undefined || badge === '') return null
  if (typeof badge === 'number') return badge > 99 ? '99+' : String(badge)
  return /^\d+$/.test(badge) && Number(badge) > 99 ? '99+' : badge
}

/**
 * The sum of the numeric badges below a node — what a folded group shows —
 * and whether one of them is red. Null when there is nothing to sum.
 */
export function sumOfBadges(item: MenuItem): { total: number; tone: string | null } | null {
  let total = 0
  let found = false
  let danger = false
  const walk = (list: MenuItem[]): void => {
    for (const child of list) {
      const n = typeof child.badge === 'number' ? child.badge : (typeof child.badge === 'string' && /^\d+$/.test(child.badge) ? Number(child.badge) : null)
      if (n !== null && n > 0) {
        total += n
        found = true
        if (child.badgeTone === 'danger') danger = true
      }
      if (child.children?.length) walk(child.children)
    }
  }
  walk(item.children ?? [])
  return found ? { total, tone: danger ? 'danger' : null } : null
}

/** Applies fresh badges by key, everywhere in the tree. */
function withBadges(list: MenuItem[], badges: CountersResponse['badges']): MenuItem[] {
  return list.map((it) => {
    const next = Object.prototype.hasOwnProperty.call(badges, it.key)
      ? { ...it, badge: badges[it.key]!.badge, badgeTone: badges[it.key]!.tone }
      : it
    return it.children?.length ? { ...next, children: withBadges(it.children, badges) } : next
  })
}

export const useMenuStore = defineStore('admin-menu', () => {
  const items = ref<MenuItem[]>([])
  const loading = ref(false)
  const error = ref<Error | null>(null)
  const isLoaded = ref(false)
  /** The top bar's "+ Create" items, already filtered by the server. */
  const quickCreate = ref<QuickCreateItem[]>([])

  /**
   * The visible items, filtered by permission through the auth store.
   * Wildcards (`*`, `admin.users.*`) work through auth.hasAnyPermission, and
   * an item with no permissions is open to everyone. A parent with no url of
   * its own and no visible children is dropped, recursively.
   */
  const visibleItems = computed<MenuItem[]>(() => {
    const auth = useAuthStore()
    const filter = (it: MenuItem): MenuItem | null => {
      if (Array.isArray(it.permissions) && it.permissions.length > 0) {
        if (!auth.hasAnyPermission(it.permissions)) return null
      }
      const filteredChildren = (it.children ?? [])
        .map(filter)
        .filter((c): c is MenuItem => c !== null)
      // A node that leads nowhere — no url, no route — and has no visible
      // children left is a dead item: it neither opens nor expands. It goes,
      // and a parent left with nothing but such nodes goes in turn.
      if (filteredChildren.length === 0 && !it.url && !it.routeName) return null
      return { ...it, children: filteredChildren }
    }
    const out: MenuItem[] = []
    for (const item of items.value) {
      const kept = filter(item)
      if (kept === null) continue
      // A section's children stand at the root under the section's title:
      // the sidebar groups them by it, and the breadcrumbs leave it out.
      if (kept.kind === 'section') {
        for (const child of kept.children ?? []) {
          out.push({ ...child, group: child.group ?? kept.label, sectionOf: kept.label })
        }
        continue
      }
      out.push(kept)
    }
    return out
  })

  /**
   * The grouped list, for rendering the sidebar in sections. Sorted by order
   * ascending; equal orders keep the server's sequence — the host's nodes in
   * the order they were added, then the auto-filled ones.
   */
  const groupedItems = computed<MenuGroup[]>(() => {
    const groups = new Map<string | null, MenuItem[]>()
    for (const item of visibleItems.value) {
      const groupKey = item.group ?? null
      if (!groups.has(groupKey)) groups.set(groupKey, [])
      groups.get(groupKey)!.push(item)
    }
    const result: MenuGroup[] = []
    for (const [group, list] of groups) {
      // Array.prototype.sort is stable: equal orders stay as they came.
      list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
      result.push({ group, items: list })
    }
    return result
  })

  /** Loads the menu from the backend; cached until reset() or force=true. */
  async function load(force = false): Promise<MenuItem[]> {
    if (isLoaded.value && !force) return items.value
    loading.value = true
    error.value = null
    try {
      const client = getAdminClient()
      const response = await client.get<MenuResponse>('/system/menu')
      items.value = Array.isArray(response.items) ? response.items : []
      quickCreate.value = Array.isArray(response.create) ? response.create : []
      if (response.groups && typeof response.groups === 'object' && !Array.isArray(response.groups)) {
        useUserNavStore().apply({ groups: response.groups })
      }
      isLoaded.value = true
      return items.value
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Sets the items directly — when the host project built them from the manifest, say. */
  function setItems(next: MenuItem[]): void {
    items.value = next
    isLoaded.value = true
    error.value = null
  }

  /**
   * Asks the server for the computed counters only (`/system/menuCounters`)
   * and applies them by key — the tree stays as it is.
   */
  async function refreshCounters(): Promise<void> {
    if (!isLoaded.value) return
    try {
      const res = await getAdminClient().get<CountersResponse>('/system/menuCounters')
      if (res && res.badges && typeof res.badges === 'object' && Object.keys(res.badges).length > 0) {
        items.value = withBadges(items.value, res.badges)
      }
    } catch {
      // A missed poll is not worth a message: the next one comes soon.
    }
  }

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let onVisible: (() => void) | null = null

  /**
   * Polls the counters every `seconds` (0 — never) and when the browser tab
   * becomes visible again. Idempotent; stopPolling() undoes it.
   */
  function startPolling(seconds: number): void {
    stopPolling()
    if (seconds > 0) {
      pollTimer = setInterval(() => {
        if (typeof document === 'undefined' || document.visibilityState !== 'hidden') void refreshCounters()
      }, seconds * 1000)
    }
    if (typeof document !== 'undefined') {
      onVisible = () => {
        if (document.visibilityState === 'visible') void refreshCounters()
      }
      document.addEventListener('visibilitychange', onVisible)
    }
  }

  function stopPolling(): void {
    if (pollTimer !== null) clearInterval(pollTimer)
    pollTimer = null
    if (onVisible !== null && typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisible)
    onVisible = null
  }

  function reset(): void {
    stopPolling()
    quickCreate.value = []
    items.value = []
    isLoaded.value = false
    error.value = null
    loading.value = false
  }

  return {
    items,
    loading,
    error,
    isLoaded,
    visibleItems,
    groupedItems,
    quickCreate,
    load,
    setItems,
    refreshCounters,
    startPolling,
    stopPolling,
    reset,
  }
})

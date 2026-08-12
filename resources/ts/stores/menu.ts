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

interface MenuResponse {
  items: MenuItem[]
}

export const useMenuStore = defineStore('admin-menu', () => {
  const items = ref<MenuItem[]>([])
  const loading = ref(false)
  const error = ref<Error | null>(null)
  const isLoaded = ref(false)

  /**
   * The visible items, filtered by permission through the auth store.
   * Wildcards (`*`, `admin.users.*`) work through auth.hasAnyPermission, and
   * an item with no permissions is open to everyone.
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
      return { ...it, children: filteredChildren }
    }
    return items.value.map(filter).filter((i): i is MenuItem => i !== null)
  })

  /**
   * The grouped list, for rendering the sidebar in sections. Sorted by order
   * ascending, then by label alphabetically.
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
      list.sort((a, b) => {
        const orderDiff = (a.order ?? 0) - (b.order ?? 0)
        if (orderDiff !== 0) return orderDiff
        return a.label.localeCompare(b.label)
      })
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

  function reset(): void {
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
    load,
    setItems,
    reset,
  }
})

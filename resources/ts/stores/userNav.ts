/**
 * useUserNavStore — the user's own navigation, kept on the server
 * (`/navigation/*`, the admin_navigation_states table):
 *   - the favourites: the star in the top bar, the block above the menu,
 *     reordered by dragging;
 *   - the recent pages and records, newest first — the ⌘K palette's empty state;
 *   - which menu groups the user folded or unfolded;
 *   - the panel's navigation settings (config admin.navigation), from the bootstrap;
 *   - the tabs open on the current page and the last query of every page, for
 *     the breadcrumbs.
 *
 * The server hands back only what the user may open right now.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient, hasAdminClient } from './registry'
import type { AdminNavigationConfig } from '../types/bootstrap'

export interface NavLink {
  /** The page's path inside the panel, with its query. */
  url: string
  label: string
  subtitle?: string | null
  icon?: string | null
  kind?: 'screen' | 'record' | 'page'
}

export interface NavState {
  favorites: NavLink[]
  recent: NavLink[]
  groups: Record<string, boolean>
  favoritesLimit?: number
}

/** A tab open on the page, as TabsLayout reports it for the breadcrumbs. */
export interface OpenTab {
  /** The address key it is kept under (`tab`), unique on a page. */
  queryKey: string
  label: string
  /** The nesting depth: an outer tab comes before an inner one. */
  depth: number
}

export const DEFAULT_NAV_CONFIG: AdminNavigationConfig = {
  pollInterval: 60,
  groupsExpanded: false,
  sumBadges: false,
  favoritesLimit: 7,
  breadcrumbs: 'full',
}

export const useUserNavStore = defineStore('admin-user-nav', () => {
  const config = ref<AdminNavigationConfig>({ ...DEFAULT_NAV_CONFIG })
  const favorites = ref<NavLink[]>([])
  const recent = ref<NavLink[]>([])
  const groups = ref<Record<string, boolean>>({})
  const loaded = ref(false)
  const error = ref<Error | null>(null)
  const openTabs = ref<OpenTab[]>([])
  /** The last query of each path, so a crumb leads back to the list as it was. */
  const lastQuery = ref<Record<string, Record<string, string | string[]>>>({})

  const favoritesFull = computed(() => favorites.value.length >= config.value.favoritesLimit)

  function configure(next: Partial<AdminNavigationConfig> | null | undefined): void {
    config.value = { ...DEFAULT_NAV_CONFIG, ...(next ?? {}) }
  }

  function apply(state: Partial<NavState> | null | undefined): void {
    if (!state) return
    if (Array.isArray(state.favorites)) favorites.value = state.favorites
    if (Array.isArray(state.recent)) recent.value = state.recent
    if (state.groups && typeof state.groups === 'object' && !Array.isArray(state.groups)) groups.value = { ...state.groups }
    if (typeof state.favoritesLimit === 'number') config.value = { ...config.value, favoritesLimit: state.favoritesLimit }
    loaded.value = true
  }

  async function load(force = false): Promise<void> {
    if ((loaded.value && !force) || !hasAdminClient()) return
    try {
      apply(await getAdminClient().get<NavState>('/navigation/state'))
      error.value = null
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
    }
  }

  function isFavorite(url: string): boolean {
    return favorites.value.some((f) => f.url === url)
  }

  async function addFavorite(link: NavLink): Promise<void> {
    apply(await getAdminClient().post<NavState>('/navigation/favorite', {
      url: link.url,
      label: link.label,
      icon: link.icon ?? null,
    }))
  }

  async function removeFavorite(url: string): Promise<void> {
    // Optimistic: the star goes out at once.
    favorites.value = favorites.value.filter((f) => f.url !== url)
    apply(await getAdminClient().post<NavState>('/navigation/unfavorite', { url }))
  }

  async function toggleFavorite(link: NavLink): Promise<void> {
    if (isFavorite(link.url)) await removeFavorite(link.url)
    else await addFavorite(link)
  }

  /** Moves the favourite at `from` to `to` and saves the order. */
  async function moveFavorite(from: number, to: number): Promise<void> {
    const list = [...favorites.value]
    if (from < 0 || from >= list.length || to < 0 || to >= list.length || from === to) return
    const [item] = list.splice(from, 1)
    list.splice(to, 0, item!)
    favorites.value = list
    apply(await getAdminClient().post<NavState>('/navigation/reorderFavorites', { urls: list.map((f) => f.url) }))
  }

  /** Notes an opened page in the recent list; silent on failure. */
  async function visit(link: NavLink): Promise<void> {
    if (!hasAdminClient()) return
    try {
      apply(await getAdminClient().post<NavState>('/navigation/visit', {
        url: link.url,
        label: link.label,
        subtitle: link.subtitle ?? null,
        kind: link.kind ?? 'page',
      }))
    } catch {
      // The recent list is a convenience: a failure stays quiet.
    }
  }

  /** The user's own choice for a group, or null when they never made one. */
  function groupOpen(key: string): boolean | null {
    const v = groups.value[key]
    return typeof v === 'boolean' ? v : null
  }

  function setGroupOpen(key: string, open: boolean): void {
    groups.value = { ...groups.value, [key]: open }
    if (!hasAdminClient()) return
    void getAdminClient().post('/navigation/group', { key, open }).catch(() => undefined)
  }

  function registerTab(tab: OpenTab): void {
    openTabs.value = [...openTabs.value.filter((t) => t.queryKey !== tab.queryKey), tab]
      .sort((a, b) => a.depth - b.depth)
  }

  function unregisterTab(queryKey: string): void {
    openTabs.value = openTabs.value.filter((t) => t.queryKey !== queryKey)
  }

  function rememberQuery(path: string, query: Record<string, unknown>): void {
    const clean: Record<string, string | string[]> = {}
    for (const [k, v] of Object.entries(query)) {
      if (typeof v === 'string') clean[k] = v
      else if (Array.isArray(v)) clean[k] = v.filter((x): x is string => typeof x === 'string')
    }
    lastQuery.value = { ...lastQuery.value, [path]: clean }
  }

  function queryOf(path: string): Record<string, string | string[]> | null {
    const q = lastQuery.value[path]
    return q && Object.keys(q).length > 0 ? q : null
  }

  function reset(): void {
    favorites.value = []
    recent.value = []
    groups.value = {}
    loaded.value = false
    openTabs.value = []
    lastQuery.value = {}
  }

  return {
    config,
    favorites,
    recent,
    groups,
    loaded,
    error,
    openTabs,
    lastQuery,
    favoritesFull,
    configure,
    apply,
    load,
    isFavorite,
    addFavorite,
    removeFavorite,
    toggleFavorite,
    moveFavorite,
    visit,
    groupOpen,
    setGroupOpen,
    registerTab,
    unregisterTab,
    rememberQuery,
    queryOf,
    reset,
  }
})

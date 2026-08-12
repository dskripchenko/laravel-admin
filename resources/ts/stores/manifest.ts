/**
 * The manifest store: it lazy-loads the admin's JSON manifest and caches it by
 * version.
 *
 * The manifest describes every resource, screen, settings page and plugin. The
 * SPA loads it once at start-up and reuses it.
 *
 * The 304 Not Modified handling is ETag-based on the backend side, so the
 * frontend does nothing special — axios and the browser handle If-None-Match.
 * The fresh version is written into lastVersion for cheap comparisons.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'

/**
 * A manifest node with a mandatory `type`. It is compatible with LayoutNode,
 * FieldNode and InfolistNode, so an array can go straight into a renderer
 * without a cast.
 */
export interface ManifestNode extends Record<string, unknown> {
  type: string
}

export interface ManifestResourceMeta {
  slug: string
  label: string
  icon?: string
  group?: string | null
  /**
   * The Eloquent morph class or the model's FQCN — AuditTimeline needs it as
   * the `subject_type` parameter of the /audit/timeline endpoint.
   */
  subject_type?: string | null
  permissions: Record<string, string>
  fields: ManifestNode[]
  /**
   * The layout of the CREATE form. It only arrives when it differs from
   * `fields` — for a resource whose tabs have nothing to show before the
   * record is saved, say.
   */
  create_fields?: ManifestNode[]
  /** The read-only entries of the view page. By default they are generated from fields(). */
  infolist?: ManifestNode[]
  columns: ManifestNode[]
  filters: ManifestNode[]
  actions: ManifestNode[]
  searchable: string[]
  with: string[]
  view_mode?: 'list' | 'tree'
  hierarchy_parent_key?: string | null
  /**
   * The slug of the resource whose index serves as the "back" context of the
   * form and view pages. null by default, meaning back leads to this
   * resource's own index. See Resource::parentSlug() on the backend.
   */
  parent_slug?: string | null
  features: Record<string, unknown>
  screens?: Record<string, unknown>
}

export interface ManifestScreenMeta {
  slug: string
  name: string
  description: string | null
  permission: string[] | string | null
}

export interface ManifestSettingsMeta {
  kind: 'settings'
  slug: string
  label: string
  permissions: Record<string, string>
  fields: ManifestNode[]
}

export interface AdminManifest {
  version: string
  locale: string
  resources: ManifestResourceMeta[]
  screens: ManifestScreenMeta[]
  settings: ManifestSettingsMeta[]
  dashboards: unknown[]
  plugins: string[]
  permissions: unknown[]
}

export const useManifestStore = defineStore('admin-manifest', () => {
  const manifest = ref<AdminManifest | null>(null)
  const loading = ref(false)
  const error = ref<Error | null>(null)
  /**
   * The boot-resolution gate. It turns true as soon as the initial "load
   * manifest → replaceManifestRoutes → router.replace(currentFullPath)" flow
   * is over, or when the manifest is known to be unnecessary (the login flow,
   * skipManifestLoad).
   *
   * AdminApp.vue uses this flag to hide NotFoundPage until the initial
   * re-resolve has finished — otherwise reloading a deep link flashes a 404
   * between the first catch-all match and the router.replace that follows.
   *
   * It is set in createAdminApp.loadAndApply()'s finally — exactly where the
   * route has been re-resolved and the manifest has either loaded or failed.
   */
  const bootResolved = ref(false)

  const isLoaded = computed(() => manifest.value !== null)
  const version = computed(() => manifest.value?.version ?? null)
  const resources = computed(() => manifest.value?.resources ?? [])
  const screens = computed(() => manifest.value?.screens ?? [])
  const settings = computed(() => manifest.value?.settings ?? [])
  const plugins = computed(() => manifest.value?.plugins ?? [])

  function getResource(slug: string): ManifestResourceMeta | null {
    return resources.value.find((r) => r.slug === slug) ?? null
  }

  function getScreen(slug: string): ManifestScreenMeta | null {
    return screens.value.find((s) => s.slug === slug) ?? null
  }

  function getSettings(slug: string): ManifestSettingsMeta | null {
    return settings.value.find((s) => s.slug === slug) ?? null
  }

  /**
   * Loads the manifest, returning the cached one when it is already there.
   * force=true refetches from the server.
   */
  /**
   * Drops the cached manifest, clearing it. Use this ONLY when the current
   * manifest is certainly not being rendered — otherwise a form or a table
   * loses its layout until the refetch. For a background update there is
   * `refresh()`.
   */
  function invalidate(): void {
    manifest.value = null
  }

  /**
   * Updates the manifest in the background WITHOUT clearing the current one:
   * `load(true)` replaces `manifest.value` atomically and only once the fresh
   * response arrives, so an open form or table keeps its layout. Called after
   * resource mutations, since the DB-driven options of selects go stale
   * otherwise. Fire-and-forget.
   */
  function refresh(): Promise<AdminManifest> {
    return load(true)
  }

  async function load(force = false): Promise<AdminManifest> {
    if (manifest.value !== null && !force) {
      return manifest.value
    }

    loading.value = true
    error.value = null
    try {
      const client = getAdminClient()
      const result = await client.get<AdminManifest>('/system/manifest')
      manifest.value = result
      return result
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  function reset(): void {
    manifest.value = null
    error.value = null
    loading.value = false
    bootResolved.value = false
  }

  return {
    // state
    manifest,
    loading,
    error,
    bootResolved,
    // getters
    isLoaded,
    version,
    resources,
    screens,
    settings,
    plugins,
    // actions
    getResource,
    getScreen,
    getSettings,
    load,
    invalidate,
    refresh,
    reset,
  }
})

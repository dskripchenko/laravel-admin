/**
 * Manifest store: lazy-load JSON-манифеста админки + кеширование по version.
 *
 * Manifest содержит описание всех Resource'ов, Screen'ов, Settings'ов,
 * Plugin'ов. SPA загружает один раз на старте и переиспользует.
 *
 * ETag-based 304 Not Modified backend-side — фронт ничего особенного не
 * делает (axios + browser handle If-None-Match). Свежий version записывается
 * в lastVersion для cheap-сравнения.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'

/**
 * Узел manifest'а с обязательным `type`. Совместим с LayoutNode/FieldNode/
 * InfolistNode — позволяет передавать массив прямо в renderer без cast'а.
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
   * Eloquent morph-class либо FQCN модели — нужен AuditTimeline'у как
   * `subject_type` параметр для /audit/timeline endpoint'а.
   */
  subject_type?: string | null
  permissions: Record<string, string>
  fields: ManifestNode[]
  /** Read-only entries для view-page. Default — auto-generated из fields(). */
  infolist?: ManifestNode[]
  columns: ManifestNode[]
  filters: ManifestNode[]
  actions: ManifestNode[]
  searchable: string[]
  with: string[]
  view_mode?: 'list' | 'tree'
  hierarchy_parent_key?: string | null
  /**
   * Slug ресурса, чей index используется как "back" контекст для form/view
   * страниц. Default null (back ведёт на собственный index). См.
   * Resource::parentSlug() на бэкенде.
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
   * Boot-resolution gate. true как только начальный flow "load manifest →
   * replaceManifestRoutes → router.replace(currentFullPath)" завершён,
   * либо если manifest заведомо не нужен (login flow / skipManifestLoad).
   *
   * AdminApp.vue использует этот флаг чтобы скрыть NotFoundPage пока не
   * закончился initial re-resolve — иначе deep-link reload даёт вспышку
   * 404 между первым match'ем catch-all и последующим router.replace.
   *
   * Проставляется createAdminApp.loadAndApply() в finally — точное место,
   * где route уже re-resolved, а manifest либо загружен, либо упал ошибкой.
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
   * Загрузить manifest. Если уже загружен — возвращает cached.
   * Force=true принудительно обновит с сервера.
   */
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
    reset,
  }
})

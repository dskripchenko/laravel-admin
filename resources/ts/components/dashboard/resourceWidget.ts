/**
 * The resource mode of the kanban and calendar widgets: the resource's meta
 * from the manifest, whether the user may see it here, and what its view is
 * given — the preset search and filters as the list parameters, and whether
 * dragging is allowed.
 *
 * The server hides a declared widget over a resource the user may not view
 * and drops such a user-added one from the saved layout; this is the same
 * check on the client, for a manifest or a layout that is older than a
 * permission change. The data itself goes through the resource's own
 * endpoints, which check again.
 */
import { computed, type ComputedRef } from 'vue'
import { useManifestStore, type ManifestResourceMeta } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { canViewResource } from './widgetConfig'

export interface ResourceWidgetProps {
  resource?: string | null
  search?: string | null
  filters?: Record<string, unknown> | unknown[] | null
  editable?: boolean | null
}

export interface ResourceWidget {
  /** The resource's meta when it may be shown here; null otherwise. */
  meta: ComputedRef<ManifestResourceMeta | null>
  /** The list parameters of the view: `q` and `filters`. */
  query: ComputedRef<Record<string, unknown>>
  /** Whether dragging may change records: editable, the view allows it, the user may update. */
  canUpdate: ComputedRef<boolean>
  canReorder: ComputedRef<boolean>
}

export function useResourceWidget(props: Readonly<ResourceWidgetProps>, view: 'kanban' | 'calendar'): ResourceWidget {
  const manifest = useManifestStore()
  const auth = useAuthStore()
  const has = (key: string): boolean => auth.hasPermission(key)

  const meta = computed<ManifestResourceMeta | null>(() => {
    const slug = props.resource
    if (!slug) return null
    const m = manifest.getResource(slug)
    if (!m || !m[view] || !canViewResource(m, has)) return null
    return m
  })

  const query = computed<Record<string, unknown>>(() => {
    const out: Record<string, unknown> = {}
    const q = (props.search ?? '').trim()
    if (q !== '') out.q = q
    // An empty PHP array arrives as [], a map as an object.
    const filters = props.filters && !Array.isArray(props.filters) ? props.filters : {}
    const map: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(filters)) {
      if (v === null || v === undefined || v === '') continue
      map[k] = Array.isArray(v) ? v.join(',') : v
    }
    if (Object.keys(map).length > 0) out.filters = map
    return out
  })

  const canUpdate = computed<boolean>(() => {
    const m = meta.value
    if (!m || props.editable !== true) return false
    if (m[view]?.readonly) return false
    if ((m.features as Record<string, unknown> | undefined)?.editable === false) return false
    const permission = m.permissions?.update
    return typeof permission !== 'string' || has(permission)
  })

  const canReorder = computed<boolean>(() => {
    const permission = meta.value?.permissions?.reorder
    return canUpdate.value && (typeof permission !== 'string' || has(permission))
  })

  return { meta, query, canUpdate, canReorder }
}

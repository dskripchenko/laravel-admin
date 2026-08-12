/**
 * The dashboard store: the edit mode, the working copy of the layout, and
 * save/load.
 *
 * The backend contract:
 *   GET  /api/admin/dashboard/get?key={dashboard_slug}   → { layout: WidgetItem[] | null }
 *   POST /api/admin/dashboard/save  body { key, widgets[] }
 *   POST /api/admin/dashboard/reset body { key }
 *
 * A WidgetItem is a per-user override:
 *   { slug, size, position, hidden, config?, type? }
 *
 * `slug` is either the backend's Widget::slug(), for the built-in ones, or
 * user-generated, for the widgets added through "Add widget" — where the host
 * saves the configuration in `config` and the type in `type`.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'

export interface WidgetLayoutItem {
  slug: string
  size?: number
  position?: number
  hidden?: boolean
  /** The widget's type: set for the user-added ones, synced with Widget for the backend ones. */
  type?: string
  /** A title override plus the per-type configuration. */
  config?: Record<string, unknown>
}

export const useDashboardStore = defineStore('admin-dashboard', () => {
  /** The current dashboard slug, the one open in DashboardPage. */
  const slug = ref<string | null>(null)
  /** The edit-mode flag; it shows the overlays on top of the widgets. */
  const editMode = ref<boolean>(false)
  /** The layout's working copy — what the user edits, before the save. */
  const draft = ref<WidgetLayoutItem[]>([])
  /** The original layout, for cancel and restore. */
  const original = ref<WidgetLayoutItem[]>([])
  /** The loading state of load and save. */
  const saving = ref<boolean>(false)
  const loading = ref<boolean>(false)
  /** The dashboard's persisted per-user period; null means the page's default. */
  const period = ref<string | null>(null)

  const isDirty = computed<boolean>(() => {
    return JSON.stringify(draft.value) !== JSON.stringify(original.value)
  })

  /** Opens a dashboard, loading the persisted layout when there is one. */
  async function openDashboard(dashboardSlug: string): Promise<void> {
    slug.value = dashboardSlug
    loading.value = true
    try {
      const client = getAdminClient()
      const result = await client.get<{ layout: WidgetLayoutItem[] | null; period?: string | null }>(
        `/dashboard/get?key=${encodeURIComponent(dashboardSlug)}`,
      )
      period.value = result.period ?? null
      const items = (result.layout ?? []).map((it, idx) => ({
        ...it,
        position: it.position ?? idx,
      }))
      original.value = items
      draft.value = items.map((it) => ({ ...it }))
    } catch {
      original.value = []
      draft.value = []
    } finally {
      loading.value = false
    }
  }

  function enterEditMode(): void {
    editMode.value = true
  }

  /**
   * Seeds the draft from the page's merged layout (see
   * DashboardPage.onEnterEdit): with an empty persisted layout, the save must
   * send the FULL list of widgets rather than an empty array.
   */
  function seedDraft(items: WidgetLayoutItem[]): void {
    if (draft.value.length === 0) {
      draft.value = items.map((it) => ({ ...it }))
    }
  }

  function cancelEdit(): void {
    draft.value = original.value.map((it) => ({ ...it }))
    editMode.value = false
  }

  async function saveLayout(): Promise<void> {
    if (slug.value === null) return
    saving.value = true
    try {
      const client = getAdminClient()
      // Before saving, the positions are renumbered in the draft's current order — the drag order.
      const widgets = draft.value.map((it, idx) => ({
        slug: it.slug,
        size: it.size,
        position: idx,
        hidden: it.hidden ?? false,
        type: it.type,
        config: it.config,
      }))
      await client.post('/dashboard/save', { key: slug.value, widgets })
      original.value = draft.value.map((it) => ({ ...it }))
      editMode.value = false
    } finally {
      saving.value = false
    }
  }

  async function resetToDefault(): Promise<void> {
    if (slug.value === null) return
    saving.value = true
    try {
      const client = getAdminClient()
      await client.post('/dashboard/reset', { key: slug.value })
      original.value = []
      draft.value = []
      editMode.value = false
    } finally {
      saving.value = false
    }
  }

  function addWidget(item: WidgetLayoutItem): void {
    draft.value = [
      ...draft.value,
      { ...item, position: draft.value.length },
    ]
  }

  function removeWidget(slugKey: string): void {
    draft.value = draft.value.filter((it) => it.slug !== slugKey)
  }

  /** Brings a widget hidden by an override back onto the dashboard. */
  function restoreWidget(slugKey: string): void {
    draft.value = draft.value.map((it) =>
      it.slug === slugKey ? { ...it, hidden: false } : it,
    )
  }

  function updateWidget(slugKey: string, patch: Partial<WidgetLayoutItem>): void {
    draft.value = draft.value.map((it) =>
      it.slug === slugKey ? { ...it, ...patch } : it,
    )
  }

  function moveWidget(fromIdx: number, toIdx: number): void {
    if (fromIdx === toIdx) return
    const next = [...draft.value]
    const [moved] = next.splice(fromIdx, 1)
    next.splice(toIdx, 0, moved)
    draft.value = next
  }

  /**
   * Replaces the draft entirely. DashboardPage.ensureDraftReflectsRendered uses
   * it to initialize the draft from what is currently rendered, before a drag
   * or a resize.
   */
  function setDraft(items: WidgetLayoutItem[]): void {
    draft.value = items
  }

  function reset(): void {
    slug.value = null
    editMode.value = false
    draft.value = []
    original.value = []
    period.value = null
  }

  /** Persists the per-user period: fire-and-forget, updating the ref optimistically. */
  async function savePeriod(key: string, value: string): Promise<void> {
    period.value = value
    try {
      await getAdminClient().post('/dashboard/savePeriod', { key, period: value })
    } catch {
      // Silent: the period is applied locally already, and the next change will save again.
    }
  }

  return {
    slug,
    editMode,
    draft,
    period,
    savePeriod,
    original,
    saving,
    loading,
    isDirty,
    openDashboard,
    enterEditMode,
    seedDraft,
    cancelEdit,
    saveLayout,
    resetToDefault,
    addWidget,
    restoreWidget,
    removeWidget,
    updateWidget,
    moveWidget,
    setDraft,
    reset,
  }
})

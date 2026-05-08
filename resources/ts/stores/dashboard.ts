/**
 * Dashboard store: edit-mode + working-copy layout + save/load.
 *
 * Backend контракт:
 *   GET  /api/admin/dashboard/get?key={dashboard_slug}   → { layout: WidgetItem[] | null }
 *   POST /api/admin/dashboard/save  body { key, widgets[] }
 *   POST /api/admin/dashboard/reset body { key }
 *
 * WidgetItem — это per-user override:
 *   { slug, size, position, hidden, config?, type? }
 *
 * `slug` — backend Widget::slug() (для встроенных) либо user-generated
 * (для виджетов добавленных через Add Widget — host сохраняет config
 * в `config` поле, type — в `type`).
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'

export interface WidgetLayoutItem {
  slug: string
  size?: number
  position?: number
  hidden?: boolean
  /** Тип widget'а (для user-added; для backend — синхронизируется с Widget). */
  type?: string
  /** Title override + per-type конфигурация. */
  config?: Record<string, unknown>
}

export const useDashboardStore = defineStore('admin-dashboard', () => {
  /** Текущий dashboard-slug (открытый в DashboardPage). */
  const slug = ref<string | null>(null)
  /** Edit-mode флаг — показывает overlay'и поверх виджетов. */
  const editMode = ref<boolean>(false)
  /** Working-copy layout (то что юзер редактирует, до save). */
  const draft = ref<WidgetLayoutItem[]>([])
  /** Изначальный layout — для cancel/restore. */
  const original = ref<WidgetLayoutItem[]>([])
  /** Loading state для load/save. */
  const saving = ref<boolean>(false)
  const loading = ref<boolean>(false)

  const isDirty = computed<boolean>(() => {
    return JSON.stringify(draft.value) !== JSON.stringify(original.value)
  })

  /** Открыть dashboard — load persisted layout если есть. */
  async function openDashboard(dashboardSlug: string): Promise<void> {
    slug.value = dashboardSlug
    loading.value = true
    try {
      const client = getAdminClient()
      const result = await client.get<{ layout: WidgetLayoutItem[] | null }>(
        `/dashboard/get?key=${encodeURIComponent(dashboardSlug)}`,
      )
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

  function cancelEdit(): void {
    draft.value = original.value.map((it) => ({ ...it }))
    editMode.value = false
  }

  async function saveLayout(): Promise<void> {
    if (slug.value === null) return
    saving.value = true
    try {
      const client = getAdminClient()
      // Перед save нумеруем position в порядке текущего draft (drag-order).
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
   * Полностью заменить draft (используется DashboardPage.ensureDraftReflectsRendered
   * для инициализации draft'а из текущего rendered-состояния перед drag/resize).
   */
  function setDraft(items: WidgetLayoutItem[]): void {
    draft.value = items
  }

  function reset(): void {
    slug.value = null
    editMode.value = false
    draft.value = []
    original.value = []
  }

  return {
    slug,
    editMode,
    draft,
    original,
    saving,
    loading,
    isDirty,
    openDashboard,
    enterEditMode,
    cancelEdit,
    saveLayout,
    resetToDefault,
    addWidget,
    removeWidget,
    updateWidget,
    moveWidget,
    setDraft,
    reset,
  }
})

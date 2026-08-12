<script setup lang="ts">
/**
 * DashboardPage — a 12-column grid of widgets with an edit mode.
 *
 * Where the widgets come from:
 *   - Manifest.dashboards, declared on the host side through DashboardScreen.
 *   - The per-user persisted layout (DashboardLayout / the dashboard store),
 *     laid over that declaration: reordering, resizing, hiding, removing, plus
 *     user-added widgets from AddWidget.
 *
 * Edit mode:
 *   1. "Edit" in the toolbar sets editMode. Every widget grows a
 *      [☰][⚙][×] overlay — see WidgetActionsOverlay.
 *   2. The ☰ handle reorders widgets through HTML5 drag.
 *   3. The ↘ handle in the bottom-right corner changes the span, 1..12.
 *   4. "+ Add widget" opens AddWidgetDialog and calls store.addWidget.
 *   5. "Save" POSTs the draft to /dashboard/save; "Cancel" restores it.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  Calendar,
  ChevronDown,
  Download,
  Pencil,
  Plus,
  RotateCcw,
} from 'lucide-vue-next'
import {
  UidButton,
  UidIcon,
  UidMenu,
  UidMenuItem,
} from '@dskripchenko/ui'
import { useManifestStore } from '../../stores/manifest'
import { useI18nStore } from '../../stores/i18n'
import { useDashboardStore, type WidgetLayoutItem } from '../../stores/dashboard'
import WidgetRenderer, { type WidgetNode } from './WidgetRenderer.vue'
import WidgetActionsOverlay from './WidgetActionsOverlay.vue'
import WidgetConfigDialog from './WidgetConfigDialog.vue'

interface DashboardManifest {
  slug: string
  label?: string
  description?: string | null
  widgets: WidgetNode[]
}

interface Props {
  slug?: string
  widgets?: WidgetNode[]
  title?: string
  subtitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  slug: undefined,
  widgets: undefined,
  title: undefined,
  subtitle: undefined,
})

const emit = defineEmits<{
  'change-period': [value: string]
  'export': []
}>()

const manifest = useManifestStore()
const dashboardStore = useDashboardStore()
const i18n = useI18nStore()
const tr = (s: string): string => i18n.tr(s)
// In standalone tests there may be no router, and useRoute() then returns
// undefined — no RouterPlugin. Fall back to an empty object.
const route = useRoute() as ReturnType<typeof useRoute> | undefined

/**
 * The slug is resolved in three steps: props → route.meta.slug →
 * route.params.slug. route.meta is the main source for the default host (see
 * buildDashboardRoute in router/builder.ts), which passes no props to the
 * component.
 */
const resolvedSlug = computed<string | undefined>(() => {
  if (props.slug) return props.slug
  const metaSlug = route?.meta?.slug
  if (typeof metaSlug === 'string' && metaSlug.length > 0) return metaSlug
  const paramSlug = route?.params?.slug
  if (typeof paramSlug === 'string' && paramSlug.length > 0) return paramSlug
  return undefined
})
const t = (key: string, fallback: string): string => {
  // Use the key when the backend's language bag has it, otherwise fall back
  // to the source string. That is what makes a gradual migration possible
  // without breaking anything.
  return i18n.has(key) ? i18n.t(key) : fallback
}

const dashboard = computed<DashboardManifest | null>(() => {
  const slug = resolvedSlug.value
  if (!slug || !manifest.manifest) return null
  const dashboards = manifest.manifest.dashboards as DashboardManifest[] | undefined
  return dashboards?.find((d) => d.slug === slug) ?? null
})

const manifestWidgets = computed<WidgetNode[]>(() => {
  if (props.widgets) return props.widgets
  // refreshedWidgets holds the data fetched after a period change through
  // /dashboard/widgets. It takes priority over the manifest snapshot.
  if (refreshedWidgets.value !== null) return refreshedWidgets.value
  return dashboard.value?.widgets ?? []
})

const resolvedTitle = computed(
  () => props.title ?? dashboard.value?.label ?? 'Dashboard',
)
const resolvedSubtitle = computed(
  () => props.subtitle ?? dashboard.value?.description ?? null,
)

/**
 * The final list of widgets with the per-user layout applied:
 *   - manifest widgets are indexed by slug;
 *   - the draft layout from the store decides order, size and hidden state;
 *   - user-added ones (they have a type and config but no manifest original)
 *     render on their own;
 *   - manifest widgets missing from the draft are appended at the end — those
 *     are the ones newly added in code.
 */
const renderedWidgets = computed<Array<{ node: WidgetNode; layoutSlug: string }>>(() => {
  const bySlug = new Map<string, WidgetNode>()
  for (const w of manifestWidgets.value) {
    const slug = String((w as Record<string, unknown>).slug ?? '')
    if (slug) bySlug.set(slug, w)
  }

  const draft = dashboardStore.draft
  const out: Array<{ node: WidgetNode; layoutSlug: string }> = []

  if (draft.length > 0) {
    for (const item of draft) {
      // IMPORTANT: mark the slug as handled in bySlug BEFORE the hidden
      // check. Otherwise a hidden override on a manifest widget does nothing:
      // the skip would leave the slug in bySlug, and the second pass would add
      // the widget straight back.
      const baseManifest = bySlug.get(item.slug) ?? null
      bySlug.delete(item.slug)
      if (item.hidden) continue
      let node: WidgetNode | null = null
      if (baseManifest !== null) {
        // A manifest widget with a per-user size override.
        node = {
          ...baseManifest,
          size: item.size ?? (baseManifest as Record<string, unknown>).size ?? 12,
        } as WidgetNode
      } else if (item.type) {
        // A user-added widget — rendered from its type and config.
        const cfg = (item.config ?? {}) as Record<string, unknown>
        node = {
          slug: item.slug,
          type: item.type,
          title: (cfg.title as string | undefined) ?? '',
          size: item.size ?? 6,
          data: cfg,
        } as WidgetNode
      }
      if (node) out.push({ node, layoutSlug: item.slug })
    }
    // Manifest widgets that are not in the persisted layout yet.
    for (const [slug, w] of bySlug.entries()) {
      out.push({ node: w, layoutSlug: slug })
    }
  } else {
    // No draft — render the manifest as it is.
    for (const w of manifestWidgets.value) {
      out.push({ node: w, layoutSlug: String((w as Record<string, unknown>).slug ?? '') })
    }
  }

  return out
})

function spanFor(w: WidgetNode): number {
  const raw = (w as Record<string, unknown>).size ?? (w as Record<string, unknown>).span
  const s = typeof raw === 'number' ? raw : 12
  return Math.max(1, Math.min(12, s))
}

/**
 * Widget height in grid rows, 1..6. Taken from:
 *   1. draft-item.config.rowSpan — the per-user override
 *   2. node.rowSpan / node.row_span — the manifest default
 *   3. a per-type fallback: chart/heatmap/recent_list/markdown = 2,
 *      stat/gauge = 1
 *
 * The height in px is grid-auto-rows (140) × rowSpan + gap × (rowSpan − 1).
 */
function rowSpanFor(layoutSlug: string, w: WidgetNode): number {
  const draftItem = dashboardStore.draft.find((it) => it.slug === layoutSlug)
  const fromDraft = (draftItem?.config as Record<string, unknown> | undefined)?.rowSpan
  if (typeof fromDraft === 'number') return Math.max(1, Math.min(6, fromDraft))
  const fromNode = (w as Record<string, unknown>).rowSpan
    ?? (w as Record<string, unknown>).row_span
  if (typeof fromNode === 'number') return Math.max(1, Math.min(6, fromNode))
  // The per-type default: big visualisations are taller, stats are compact.
  const type = String((w as Record<string, unknown>).type ?? '')
  if (type === 'stats' || type === 'stat') return 1
  if (type === 'gauge') return 2
  if (type === 'chart' || type === 'bar-chart' || type === 'donut-chart') return 2
  if (type === 'heatmap' || type === 'recent_list' || type === 'recent-list' || type === 'recent-table') return 2
  if (type === 'markdown' || type === 'iframe' || type === 'table') return 2
  return 1
}

// === Toolbar period ===
const periods = computed(() => [
  { key: '7d', label: t('admin.dashboard.period.7d', 'За 7 дней') },
  { key: '30d', label: t('admin.dashboard.period.30d', 'За 30 дней') },
  { key: '90d', label: t('admin.dashboard.period.90d', 'За 90 дней') },
  { key: 'all', label: t('admin.dashboard.period.all', 'Всё время') },
])
const selectedPeriod = ref<string>('30d')
/** Fresh widget data fetched through /dashboard/widgets?period=. */
const refreshedWidgets = ref<WidgetNode[] | null>(null)

async function setPeriod(key: string, close: () => void): Promise<void> {
  selectedPeriod.value = key
  emit('change-period', key)
  close()
  // Persist the choice per user, fire-and-forget, so it survives a reload.
  const slug = resolvedSlug.value
  if (slug) void dashboardStore.savePeriod(slug, key)
  await refetchPeriod()
}

async function refetchPeriod(): Promise<void> {
  const slug = resolvedSlug.value
  if (!slug) return
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ widgets: WidgetNode[]; period: string }>(
      `/dashboard/widgets?key=${encodeURIComponent(slug)}&period=${encodeURIComponent(selectedPeriod.value)}`,
    )
    refreshedWidgets.value = result.widgets
  } catch {
    // Silent: keep the manifest data.
  }
}

// === Auto-refresh polling ===
// A widget may declare `refresh` in seconds (Widget::refresh()). We run a
// single setInterval at the smallest refresh among the widgets; every tick
// hits /dashboard/widgets and updates refreshedWidgets.
let pollingTimer: ReturnType<typeof setInterval> | null = null

const minRefreshSec = computed<number>(() => {
  let min = 0
  for (const w of manifestWidgets.value) {
    const r = (w as Record<string, unknown>).refresh
    if (typeof r === 'number' && r > 0) {
      if (min === 0 || r < min) min = r
    }
  }
  return min
})

function startPolling(): void {
  stopPolling()
  const sec = minRefreshSec.value
  if (sec <= 0) return
  pollingTimer = setInterval(() => {
    void refetchPeriod()
  }, sec * 1000)
}
function stopPolling(): void {
  if (pollingTimer !== null) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}
// Restart the polling when minRefreshSec changes — new widgets arrived.
watch(
  () => minRefreshSec.value,
  () => startPolling(),
)

// Lifecycle: load the manifest if it is not there yet, and open the dashboard
// in the store so the persisted layout is pulled in. The watch on the slug
// keeps navigation between different dashboards working inside the SPA.
onMounted(async () => {
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  const slug = resolvedSlug.value
  if (slug) {
    await dashboardStore.openDashboard(slug).catch(() => undefined)
    // Restore the period this user had chosen.
    if (dashboardStore.period) {
      selectedPeriod.value = dashboardStore.period
      await refetchPeriod()
    }
  }
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})

watch(
  () => resolvedSlug.value,
  async (next, prev) => {
    if (next === prev) return
    if (next) {
      await dashboardStore.openDashboard(next).catch(() => undefined)
      if (dashboardStore.period) {
        selectedPeriod.value = dashboardStore.period
        await refetchPeriod()
      }
    } else {
      dashboardStore.reset()
    }
  },
)

const periodLabel = computed(
  () => periods.value.find((p) => p.key === selectedPeriod.value)?.label ?? t('admin.dashboard.period.label', 'Период'),
)

// === Edit-mode actions ===
const dialogMode = ref<'add' | 'configure' | null>(null)
const dialogItem = ref<WidgetLayoutItem | null>(null)
const dialogInitialTitle = ref<string>('')
function openAdd(): void {
  dialogMode.value = 'add'
  dialogItem.value = null
  dialogInitialTitle.value = ''
}
function closeDialog(): void {
  dialogMode.value = null
}

function onEnterEdit(): void {
  // An empty draft — no persisted layout yet — is seeded with the current
  // merged view. Without this the first save sent widgets:[] and failed the
  // required validation.
  if (dashboardStore.draft.length === 0) {
    dashboardStore.seedDraft(
      renderedWidgets.value.map(({ node, layoutSlug }) => ({
        slug: layoutSlug,
        size: spanFor(node),
        type: String((node as Record<string, unknown>).type ?? '') || undefined,
      })),
    )
  }
  dashboardStore.enterEditMode()
}
function onCancelEdit(): void {
  dashboardStore.cancelEdit()
}
async function onResetLayout(): Promise<void> {
  // Reset to the dashboard's default layout: the persisted record is deleted.
  if (!confirm(t('admin.dashboard.reset_confirm', 'Сбросить layout к настройкам по умолчанию?'))) return
  await dashboardStore.resetToDefault().catch(() => undefined)
}
async function onSaveLayout(): Promise<void> {
  await dashboardStore.saveLayout().catch(() => undefined)
}

function onRemoveWidget(layoutSlug: string): void {
  // A manifest widget cannot be dropped from the draft for good: the render
  // merge brings it back, since widgets with no layout record are appended at
  // the end. So a manifest widget always gets a hidden override; outright
  // removal is only for user-added ones.
  const isManifest = manifestWidgets.value.some(
    (w) => String((w as Record<string, unknown>).slug ?? '') === layoutSlug,
  )
  const inDraft = dashboardStore.draft.some((it) => it.slug === layoutSlug)
  if (isManifest) {
    if (inDraft) {
      dashboardStore.updateWidget(layoutSlug, { hidden: true })
    } else {
      dashboardStore.addWidget({ slug: layoutSlug, hidden: true })
    }
  } else {
    dashboardStore.removeWidget(layoutSlug)
  }
}

function onConfigureWidget(layoutSlug: string): void {
  ensureDraftReflectsRendered()
  const draftItem = dashboardStore.draft.find((it) => it.slug === layoutSlug) ?? null
  // For manifest widgets that have not reached the draft yet, the initial
  // state is taken from renderedWidgets — the node there is already resolved.
  const rendered = renderedWidgets.value.find((r) => r.layoutSlug === layoutSlug)
  const node = rendered?.node as Record<string, unknown> | undefined
  dialogItem.value = draftItem ?? {
    slug: layoutSlug,
    type: (node?.type as string | undefined) ?? '',
    size: (node?.size as number | undefined) ?? 6,
    config: {},
  }
  dialogInitialTitle.value = (node?.title as string | undefined) ?? ''
  dialogMode.value = 'configure'
}

function onAddWidget(item: WidgetLayoutItem): void {
  dashboardStore.addWidget(item)
}

/**
 * Widgets hidden by an override — the "restore" section of the add dialog.
 * The title comes from the manifest; for user-added ones from config.title,
 * and failing that the slug.
 */
const restorableWidgets = computed<Array<{ slug: string; title: string }>>(() => {
  const bySlug = new Map<string, WidgetNode>()
  for (const w of manifestWidgets.value) {
    const s = String((w as Record<string, unknown>).slug ?? '')
    if (s) bySlug.set(s, w)
  }
  return dashboardStore.draft
    .filter((it) => it.hidden === true)
    .map((it) => {
      const node = bySlug.get(it.slug) as Record<string, unknown> | undefined
      const cfg = (it.config ?? {}) as Record<string, unknown>
      return {
        slug: it.slug,
        title: (node?.title as string | undefined) || (cfg.title as string | undefined) || it.slug,
      }
    })
})

function onRestoreWidget(slug: string): void {
  dashboardStore.restoreWidget(slug)
}

function onSaveConfig(patch: Partial<WidgetLayoutItem>): void {
  if (!dialogItem.value) return
  const slug = dialogItem.value.slug
  // A widget not yet in the draft is added; otherwise it is patched.
  const inDraft = dashboardStore.draft.some((it) => it.slug === slug)
  if (inDraft) {
    // Merge the config with the previous one rather than replacing it.
    const existing = dashboardStore.draft.find((it) => it.slug === slug)
    dashboardStore.updateWidget(slug, {
      ...patch,
      config: { ...(existing?.config ?? {}), ...(patch.config ?? {}) },
    })
  } else {
    dashboardStore.addWidget({
      slug,
      type: dialogItem.value.type,
      ...patch,
    })
  }
}

// === Drag reordering, native HTML5 ===
const dragSourceIdx = ref<number | null>(null)
/** The index the drop indicator points at while dragging over a cell. */
const dragOverIdx = ref<number | null>(null)
/**
 * In native HTML5 drag the `dragstart` target is the element carrying
 * draggable=true — here admin-dashboard__cell — and NOT the inner drag handle.
 * A closest('[data-drag-handle]') check inside dragstart is therefore always
 * null.
 *
 * The way round it: pointerdown fires BEFORE dragstart and its target IS the
 * innermost element (the svg or the button). We remember whether pointerdown
 * landed on a drag handle, and dragstart consults that flag.
 */
const dragInitiated = ref<boolean>(false)
function onPointerDown(e: PointerEvent): void {
  if (!dashboardStore.editMode) {
    dragInitiated.value = false
    return
  }
  const target = e.target as HTMLElement | null
  dragInitiated.value = !!target?.closest('[data-drag-handle="true"]')
}
function onDragStart(idx: number, e: DragEvent): void {
  if (!dashboardStore.editMode) return
  if (e.dataTransfer === null) return
  if (!dragInitiated.value) {
    // pointerdown was not on a drag handle — on the widget body, say — so the
    // drag is refused. Otherwise any click in edit mode would drag the card.
    e.preventDefault()
    return
  }
  dragSourceIdx.value = idx
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(idx))
}
function onDragEnd(): void {
  // Clear the flags whichever way the drag ended.
  dragInitiated.value = false
  dragSourceIdx.value = null
  dragOverIdx.value = null
}
function onDragOver(toIdx: number, e: DragEvent): void {
  if (dashboardStore.editMode && dragSourceIdx.value !== null) {
    e.preventDefault()
    if (dragOverIdx.value !== toIdx) dragOverIdx.value = toIdx
  }
}
function onDrop(toIdx: number, e: DragEvent): void {
  e.preventDefault()
  if (!dashboardStore.editMode || dragSourceIdx.value === null) return
  // Reorder in the store. A widget absent from the draft is lifted out of the
  // manifest first, so that the layout keeps its position.
  const sourceIdx = dragSourceIdx.value
  ensureDraftReflectsRendered()
  dashboardStore.moveWidget(sourceIdx, toIdx)
  dragSourceIdx.value = null
  dragOverIdx.value = null
}

/**
 * Before a drag or a resize, make sure the currently rendered order is
 * reflected in store.draft — otherwise the reorder operates on an empty draft
 * and loses the manifest widgets.
 */
function ensureDraftReflectsRendered(): void {
  // renderedWidgets holds no hidden widgets, so their records are preserved
  // separately: rebuilding the draft without them would quietly resurrect
  // everything the user had hidden.
  const hiddenItems = dashboardStore.draft.filter((it) => it.hidden === true)
  if (dashboardStore.draft.length === renderedWidgets.value.length + hiddenItems.length) return
  const items: WidgetLayoutItem[] = renderedWidgets.value.map(({ node, layoutSlug }, idx) => {
    const existing = dashboardStore.draft.find((it) => it.slug === layoutSlug)
    return {
      slug: layoutSlug,
      size: spanFor(node),
      position: idx,
      hidden: false,
      type: existing?.type ?? (node as Record<string, unknown>).type as string | undefined,
      config: existing?.config,
    }
  })
  dashboardStore.setDraft([...items, ...hiddenItems.map((it) => ({ ...it }))])
}

// === Mouse resize, on both axes: width in columns, height in rows ===
const ROW_HEIGHT_PX = 140
const ROW_GAP_PX = 16 // совпадает с --uid-space-md (по grid gap)

interface Resizing {
  slug: string
  startX: number
  startY: number
  startSpan: number
  startRowSpan: number
}
const resizing = ref<Resizing | null>(null)
let resizeContainerWidth = 0
function onResizeStart(
  e: MouseEvent,
  layoutSlug: string,
  currentSpan: number,
  currentRowSpan: number,
): void {
  if (!dashboardStore.editMode) return
  e.preventDefault()
  e.stopPropagation()
  ensureDraftReflectsRendered()
  resizing.value = {
    slug: layoutSlug,
    startX: e.clientX,
    startY: e.clientY,
    startSpan: currentSpan,
    startRowSpan: currentRowSpan,
  }
  const grid = (e.target as HTMLElement).closest('.admin-dashboard__grid') as HTMLElement | null
  resizeContainerWidth = grid?.getBoundingClientRect().width ?? 1200
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', onResizeEnd)
}
function onResizeMove(e: MouseEvent): void {
  if (!resizing.value) return
  // X-axis → cols span (1..12)
  const colWidth = resizeContainerWidth / 12
  const dx = Math.round((e.clientX - resizing.value.startX) / colWidth)
  const nextSpan = Math.max(1, Math.min(12, resizing.value.startSpan + dx))

  // The Y axis maps to a row span of 1..6; one step is ROW_HEIGHT_PX + ROW_GAP_PX.
  const rowStep = ROW_HEIGHT_PX + ROW_GAP_PX
  const dy = Math.round((e.clientY - resizing.value.startY) / rowStep)
  const nextRowSpan = Math.max(1, Math.min(6, resizing.value.startRowSpan + dy))

  const item = dashboardStore.draft.find((it) => it.slug === resizing.value!.slug)
  if (!item) return
  const currentRowSpan = (item.config as Record<string, unknown> | undefined)?.rowSpan
  const patch: Partial<WidgetLayoutItem> = {}
  if (item.size !== nextSpan) patch.size = nextSpan
  if (currentRowSpan !== nextRowSpan) {
    patch.config = { ...(item.config ?? {}), rowSpan: nextRowSpan }
  }
  if (Object.keys(patch).length > 0) {
    dashboardStore.updateWidget(resizing.value.slug, patch)
  }
}
function onResizeEnd(): void {
  resizing.value = null
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', onResizeEnd)
}

function onExport(): void {
  // Export the current slice of the dashboard — widgets, their data and the
  // period — into a JSON file. A host may listen to 'export' and write its own
  // format instead.
  emit('export')
  if (typeof window === 'undefined' || typeof document === 'undefined') return

  const snapshot = {
    dashboard: resolvedSlug.value,
    period: selectedPeriod.value,
    exported_at: new Date().toISOString(),
    widgets: renderedWidgets.value.map(({ node }) => {
      const n = node as Record<string, unknown>
      return {
        type: n.type,
        title: n.title ?? (n.data as Record<string, unknown> | undefined)?.title ?? null,
        data: n.data ?? null,
      }
    }),
  }

  const blob = new Blob([JSON.stringify(snapshot, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `dashboard-${resolvedSlug.value ?? 'export'}-${selectedPeriod.value}.json`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <section class="admin-page admin-dashboard">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ resolvedTitle }}</h1>
        <div v-if="resolvedSubtitle" class="admin-page__count">
          {{ resolvedSubtitle }}
        </div>
      </div>
      <div class="admin-page__actions">
        <slot name="actions" />

        <UidMenu>
          <template #trigger>
            <UidButton variant="ghost" size="md" data-testid="dash-period">
              <template #prepend><UidIcon :icon="Calendar" :size="14" /></template>
              {{ periodLabel }}
              <template #append><UidIcon :icon="ChevronDown" :size="14" /></template>
            </UidButton>
          </template>
          <UidMenuItem
            v-for="p in periods"
            :key="p.key"
            @click="setPeriod(p.key, () => undefined)"
          >
            {{ p.label }}
          </UidMenuItem>
        </UidMenu>

        <UidButton variant="secondary" size="md" data-testid="dash-export" @click="onExport">
          <template #prepend><UidIcon :icon="Download" :size="14" /></template>
          {{ t('admin.dashboard.export', 'Экспорт') }}
        </UidButton>

        <!-- Edit-mode toggle -->
        <template v-if="!dashboardStore.editMode">
          <UidButton variant="secondary" size="md" data-testid="dash-edit" @click="onEnterEdit">
            <template #prepend><UidIcon :icon="Pencil" :size="14" /></template>
            {{ t('admin.dashboard.edit_layout', 'Редактировать') }}
          </UidButton>
        </template>
        <template v-else>
          <UidButton variant="secondary" size="md" data-testid="dash-add-widget" @click="openAdd">
            <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
            {{ t('admin.dashboard.add_widget', 'Добавить виджет') }}
          </UidButton>
          <UidButton variant="ghost" size="md" @click="onResetLayout">
            <template #prepend><UidIcon :icon="RotateCcw" :size="14" /></template>
            {{ t('admin.dashboard.reset_layout', 'Сбросить') }}
          </UidButton>
          <UidButton variant="ghost" size="md" @click="onCancelEdit">
            {{ t('admin.dashboard.cancel_edit', 'Отмена') }}
          </UidButton>
          <UidButton
            variant="primary"
            size="md"
            :loading="dashboardStore.saving"
            :disabled="dashboardStore.saving"
            @click="onSaveLayout"
          >
            {{ t('admin.dashboard.save_layout', 'Сохранить') }}
          </UidButton>
        </template>
      </div>
    </header>

    <div
      :class="[
        'admin-dashboard__grid',
        { 'admin-dashboard__grid--editing': dashboardStore.editMode },
      ]"
    >
      <div
        v-for="(item, idx) in renderedWidgets"
        :key="item.layoutSlug"
        class="admin-dashboard__cell"
        :class="{
          'admin-dashboard__cell--editing': dashboardStore.editMode,
          'admin-dashboard__cell--dragging': dragSourceIdx === idx,
          'admin-dashboard__cell--drop-target': dragOverIdx === idx && dragSourceIdx !== idx && dragSourceIdx !== null,
        }"
        :draggable="dashboardStore.editMode"
        :style="{
          gridColumn: `span ${spanFor(item.node)} / span ${spanFor(item.node)}`,
          gridRow: `span ${rowSpanFor(item.layoutSlug, item.node)} / span ${rowSpanFor(item.layoutSlug, item.node)}`,
        }"
        @pointerdown="onPointerDown"
        @dragstart="onDragStart(idx, $event)"
        @dragover="onDragOver(idx, $event)"
        @drop="onDrop(idx, $event)"
        @dragend="onDragEnd"
      >
        <WidgetRenderer :node="item.node" />
        <WidgetActionsOverlay
          v-if="dashboardStore.editMode"
          @configure="onConfigureWidget(item.layoutSlug)"
          @remove="onRemoveWidget(item.layoutSlug)"
        />
        <span
          v-if="dashboardStore.editMode"
          class="admin-dashboard__resize"
          :aria-label="tr('Изменить размер')"
          @mousedown="onResizeStart($event, item.layoutSlug, spanFor(item.node), rowSpanFor(item.layoutSlug, item.node))"
        />
      </div>
    </div>

    <WidgetConfigDialog
      :open="dialogMode !== null"
      :mode="dialogMode ?? 'add'"
      :item="dialogItem"
      :initial-title="dialogInitialTitle"
      :restorable="restorableWidgets"
      @close="closeDialog"
      @add="onAddWidget"
      @save="onSaveConfig"
      @restore="onRestoreWidget"
    />
  </section>
</template>

<style>
.admin-dashboard__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  /*
   * grid-auto-rows is fixed — otherwise rowSpan would mean nothing. The 140px
   * step is chosen so that one row fits the smallest stat card, two rows fit a
   * chart or a table, and three or more fit the larger widgets.
   */
  grid-auto-rows: 140px;
  gap: var(--uid-space-md);
}
/*
 * Narrow screens: twelve columns across 390px turn the widgets into vertical
 * strips, and the ones on the right run off the edge — unreadable and
 * unscrollable, because the host hides the overflow. Below the drawer
 * threshold (768px, the same as UidSidebarLayout) the widgets go into a single
 * column and the row height stops being fixed: the content does not fit into
 * 140px when the width is a third of what it was designed for.
 */
@media (max-width: 768px) {
  .admin-dashboard__grid {
    grid-template-columns: 1fr;
    grid-auto-rows: minmax(140px, auto);
  }

  .admin-dashboard__cell {
    grid-column: 1 / -1 !important;
  }
}

.admin-dashboard__grid--editing .admin-dashboard__cell {
  outline: 1px dashed transparent;
  transition: outline-color 120ms ease, opacity 120ms ease;
}
.admin-dashboard__grid--editing .admin-dashboard__cell:hover {
  outline-color: var(--uid-accent);
}
/* The dragged source goes translucent, so it is clear where it will land. */
.admin-dashboard__cell--dragging {
  opacity: 0.45;
}
/* The cell under the cursor is marked with an accent bar as the drop target. */
.admin-dashboard__cell--drop-target {
  outline: 2px solid var(--uid-color-primary, var(--uid-accent, #14b8a6)) !important;
  outline-offset: -2px;
  background: color-mix(in srgb, var(--uid-color-primary, #14b8a6) 6%, transparent);
}
.admin-dashboard__cell {
  position: relative;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
/*
 * The inner WidgetRenderer and whatever the widget draws must fill the cell's
 * full height. All descendants stretch through flex; the UidCard inside widget
 * components gets height:100% either through the `.admin-widget` class, used
 * by all the common widgets, or directly through the `.uid-card`/`.uid-stat`
 * flex fallback.
 */
.admin-dashboard__cell > * {
  flex: 1 1 auto;
  min-height: 0;
}
.admin-dashboard__cell .admin-widget,
.admin-dashboard__cell > .uid-card,
.admin-dashboard__cell > .uid-stat,
.admin-dashboard__cell .admin-widget > .uid-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}
/*
 * UidCard's __body holds the card's main content. It is stretched so that a
 * chart, markdown or table fills the vertical space available.
 */
.admin-dashboard__cell .uid-card__body,
.admin-dashboard__cell .admin-widget__body {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
/*
 * cursor: default on the cell — a card is dragged only by its [☰] handle, see
 * WidgetActionsOverlay. That keeps the interactive elements inside a widget
 * usable while edit mode is on.
 */
.admin-dashboard__resize {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 16px;
  height: 16px;
  cursor: nwse-resize;
  background:
    linear-gradient(135deg, transparent 0 50%, var(--uid-text-tertiary) 50% 60%, transparent 60% 70%, var(--uid-text-tertiary) 70% 80%, transparent 80%);
  z-index: 4;
  border-radius: 2px;
  /*
   * The transparent hit area is a little larger, so the handle is easier to
   * catch.
   */
}
.admin-dashboard__resize:hover {
  background:
    linear-gradient(135deg, transparent 0 45%, var(--uid-color-primary, var(--uid-text-primary)) 45% 60%, transparent 60% 70%, var(--uid-color-primary, var(--uid-text-primary)) 70% 85%, transparent 85%);
}
</style>

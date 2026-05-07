<script setup lang="ts">
/**
 * DashboardPage — 12-col grid из widget'ов с поддержкой edit-mode.
 *
 * Источник widget'ов:
 *   - Manifest.dashboards (host-side declared, через DashboardScreen).
 *   - Per-user persisted layout (DashboardLayout / dashboard store) —
 *     накладывается поверх manifest'ной декларации: переупорядочение,
 *     resize, hidden, удаление + user-added widgets через AddWidget.
 *
 * Edit-mode:
 *   1. «Редактировать» в toolbar → editMode=true. На каждом widget'е
 *      появляются [☰][⚙][×] (см. WidgetActionsOverlay).
 *   2. Drag-handle ☰ позволяет менять порядок (HTML5 drag).
 *   3. Resize-handle ↘ в правом-нижнем углу — изменяет span (1..12).
 *   4. + Add widget → AddWidgetDialog → store.addWidget.
 *   5. «Сохранить» → POST /dashboard/save с draft. «Отменить» → restore.
 */
import { computed, ref } from 'vue'
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

const dashboard = computed<DashboardManifest | null>(() => {
  if (!props.slug || !manifest.manifest) return null
  const dashboards = manifest.manifest.dashboards as DashboardManifest[] | undefined
  return dashboards?.find((d) => d.slug === props.slug) ?? null
})

const manifestWidgets = computed<WidgetNode[]>(() => {
  if (props.widgets) return props.widgets
  // refreshedWidgets — свежие data после смены period (через /dashboard/widgets).
  // Имеют приоритет над manifest'ным snapshot'ом.
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
 * Финальный список виджетов с применённым per-user layout'ом:
 *   - manifest widgets индексируются по slug;
 *   - draft layout (из store) задаёт порядок, size, hidden;
 *   - user-added (есть type/config, но нет manifest-исходника) рендерятся как самостоятельные;
 *   - manifest widget'ы которых нет в draft — добавляются в конец (новые в коде).
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
      if (item.hidden) continue
      let node: WidgetNode | null = null
      if (bySlug.has(item.slug)) {
        // Manifest-widget с per-user override size.
        const base = bySlug.get(item.slug) as WidgetNode
        node = {
          ...base,
          size: item.size ?? (base as Record<string, unknown>).size ?? 12,
        } as WidgetNode
        bySlug.delete(item.slug)
      } else if (item.type) {
        // User-added widget — рендерится по type + config.
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
    // Новые manifest-widgets (которых ещё нет в persisted layout'е).
    for (const [slug, w] of bySlug.entries()) {
      out.push({ node: w, layoutSlug: slug })
    }
  } else {
    // Нет draft'а — рендерим manifest как есть.
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

// === Toolbar period ===
const periods = [
  { key: '7d', label: 'За 7 дней' },
  { key: '30d', label: 'За 30 дней' },
  { key: '90d', label: 'За 90 дней' },
  { key: 'all', label: 'Всё время' },
]
const selectedPeriod = ref<string>('30d')
/** Свежие widget data полученные через /dashboard/widgets?period=. */
const refreshedWidgets = ref<WidgetNode[] | null>(null)

async function setPeriod(key: string, close: () => void): Promise<void> {
  selectedPeriod.value = key
  emit('change-period', key)
  close()
  await refetchPeriod()
}

async function refetchPeriod(): Promise<void> {
  if (!props.slug) return
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    const result = await client.get<{ widgets: WidgetNode[]; period: string }>(
      `/dashboard/widgets?key=${encodeURIComponent(props.slug)}&period=${encodeURIComponent(selectedPeriod.value)}`,
    )
    refreshedWidgets.value = result.widgets
  } catch {
    // silent — оставляем manifest-данные.
  }
}

const periodLabel = computed(
  () => periods.find((p) => p.key === selectedPeriod.value)?.label ?? 'Период',
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
  dashboardStore.enterEditMode()
}
function onCancelEdit(): void {
  dashboardStore.cancelEdit()
}
async function onSaveLayout(): Promise<void> {
  await dashboardStore.saveLayout().catch(() => undefined)
}

function onRemoveWidget(layoutSlug: string): void {
  // Если widget есть в draft — удаляем, иначе делаем hidden override.
  const inDraft = dashboardStore.draft.some((it) => it.slug === layoutSlug)
  if (inDraft) {
    dashboardStore.removeWidget(layoutSlug)
  } else {
    // Manifest widget — добавляем как hidden override.
    dashboardStore.addWidget({ slug: layoutSlug, hidden: true })
  }
}

function onConfigureWidget(layoutSlug: string): void {
  ensureDraftReflectsRendered()
  const draftItem = dashboardStore.draft.find((it) => it.slug === layoutSlug) ?? null
  // Для manifest-widget'ов (которые ещё не попали в draft) собираем
  // initial-state из renderedWidgets — там уже посчитанный node.
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

function onSaveConfig(patch: Partial<WidgetLayoutItem>): void {
  if (!dialogItem.value) return
  const slug = dialogItem.value.slug
  // Если widget ещё не в draft — добавляем; иначе patch.
  const inDraft = dashboardStore.draft.some((it) => it.slug === slug)
  if (inDraft) {
    // merge config с предыдущим (а не replace).
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

// === Drag-reorder (нативный HTML5) ===
const dragSourceIdx = ref<number | null>(null)
function onDragStart(idx: number, e: DragEvent): void {
  if (!dashboardStore.editMode) return
  if (e.dataTransfer === null) return
  // Разрешаем drag только если он начался с [data-drag-handle] —
  // иначе пользователь не сможет кликать по контенту виджета (selectable
  // text, ссылки, кнопки) без случайного перетаскивания всей карточки.
  const target = e.target as HTMLElement | null
  if (!target?.closest('[data-drag-handle="true"]')) {
    e.preventDefault()
    return
  }
  dragSourceIdx.value = idx
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(idx))
}
function onDragOver(e: DragEvent): void {
  if (dashboardStore.editMode && dragSourceIdx.value !== null) {
    e.preventDefault()
  }
}
function onDrop(toIdx: number, e: DragEvent): void {
  e.preventDefault()
  if (!dashboardStore.editMode || dragSourceIdx.value === null) return
  // Reorder в store. Если widget не в draft — сначала "поднимаем" его
  // из manifest'а, чтобы layout сохранил позицию.
  const sourceIdx = dragSourceIdx.value
  ensureDraftReflectsRendered()
  dashboardStore.moveWidget(sourceIdx, toIdx)
  dragSourceIdx.value = null
}

/**
 * Перед drag/resize гарантируем, что текущий rendered порядок отражён
 * в store.draft (иначе reorder работает на пустом draft'е и теряет
 * manifest widget'ы).
 */
function ensureDraftReflectsRendered(): void {
  if (dashboardStore.draft.length === renderedWidgets.value.length) return
  const items: WidgetLayoutItem[] = renderedWidgets.value.map(({ node, layoutSlug }, idx) => {
    const existing = dashboardStore.draft.find((it) => it.slug === layoutSlug)
    return {
      slug: layoutSlug,
      size: spanFor(node),
      position: idx,
      hidden: existing?.hidden ?? false,
      type: existing?.type ?? (node as Record<string, unknown>).type as string | undefined,
      config: existing?.config,
    }
  })
  dashboardStore.draft = items
}

// === Resize via mouse ===
const resizing = ref<{ slug: string; startX: number; startSpan: number } | null>(null)
let resizeContainerWidth = 0
function onResizeStart(e: MouseEvent, layoutSlug: string, currentSpan: number): void {
  if (!dashboardStore.editMode) return
  e.preventDefault()
  e.stopPropagation()
  ensureDraftReflectsRendered()
  resizing.value = { slug: layoutSlug, startX: e.clientX, startSpan: currentSpan }
  const grid = (e.target as HTMLElement).closest('.admin-dashboard__grid') as HTMLElement | null
  resizeContainerWidth = grid?.getBoundingClientRect().width ?? 1200
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', onResizeEnd)
}
function onResizeMove(e: MouseEvent): void {
  if (!resizing.value) return
  const colWidth = resizeContainerWidth / 12
  const delta = Math.round((e.clientX - resizing.value.startX) / colWidth)
  const nextSpan = Math.max(1, Math.min(12, resizing.value.startSpan + delta))
  const item = dashboardStore.draft.find((it) => it.slug === resizing.value!.slug)
  if (item && item.size !== nextSpan) {
    dashboardStore.updateWidget(resizing.value.slug, { size: nextSpan })
  }
}
function onResizeEnd(): void {
  resizing.value = null
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', onResizeEnd)
}

function onExport(): void {
  emit('export')
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
            <UidButton variant="ghost" size="md">
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

        <UidButton variant="secondary" size="md" @click="onExport">
          <template #prepend><UidIcon :icon="Download" :size="14" /></template>
          Export
        </UidButton>

        <!-- Edit-mode toggle -->
        <template v-if="!dashboardStore.editMode">
          <UidButton variant="secondary" size="md" @click="onEnterEdit">
            <template #prepend><UidIcon :icon="Pencil" :size="14" /></template>
            Редактировать
          </UidButton>
        </template>
        <template v-else>
          <UidButton variant="secondary" size="md" @click="openAdd">
            <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
            Add widget
          </UidButton>
          <UidButton variant="ghost" size="md" @click="onCancelEdit">
            <template #prepend><UidIcon :icon="RotateCcw" :size="14" /></template>
            Отмена
          </UidButton>
          <UidButton
            variant="primary"
            size="md"
            :loading="dashboardStore.saving"
            :disabled="dashboardStore.saving"
            @click="onSaveLayout"
          >
            Сохранить
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
        :class="{ 'admin-dashboard__cell--editing': dashboardStore.editMode }"
        :draggable="dashboardStore.editMode"
        :style="{ gridColumn: `span ${spanFor(item.node)} / span ${spanFor(item.node)}` }"
        @dragstart="onDragStart(idx, $event)"
        @dragover="onDragOver"
        @drop="onDrop(idx, $event)"
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
          aria-label="Изменить размер"
          @mousedown="onResizeStart($event, item.layoutSlug, spanFor(item.node))"
        />
      </div>
    </div>

    <WidgetConfigDialog
      :open="dialogMode !== null"
      :mode="dialogMode ?? 'add'"
      :item="dialogItem"
      :initial-title="dialogInitialTitle"
      @close="closeDialog"
      @add="onAddWidget"
      @save="onSaveConfig"
    />
  </section>
</template>

<style>
.admin-dashboard__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--uid-space-md);
}
.admin-dashboard__grid--editing .admin-dashboard__cell {
  outline: 1px dashed transparent;
  transition: outline-color 120ms ease;
}
.admin-dashboard__grid--editing .admin-dashboard__cell:hover {
  outline-color: var(--uid-accent);
}
.admin-dashboard__cell {
  position: relative;
  min-width: 0;
}
/*
 * cursor: default на cell — дёргать карточку можно только за [☰]-handle
 * (см. WidgetActionsOverlay). Это позволяет в edit-mode по-прежнему
 * взаимодействовать с интерактивными элементами внутри виджета.
 */
.admin-dashboard__resize {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 14px;
  height: 14px;
  cursor: nwse-resize;
  background:
    linear-gradient(135deg, transparent 0 50%, var(--uid-text-tertiary) 50% 60%, transparent 60% 70%, var(--uid-text-tertiary) 70% 80%, transparent 80%);
  z-index: 4;
}
</style>

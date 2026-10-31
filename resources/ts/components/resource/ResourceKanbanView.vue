<script setup lang="ts">
/**
 * ResourceKanbanView — the kanban view of a resource's index page
 * (Resource::kanban() on the backend), drawn with the kit's UidBoard.
 *
 * The cards come from POST /{slug}/board with the list's search and filters,
 * a page per column; the column header's "more" fetches the next page of that
 * column. A drop is applied at once and saved through POST /{slug}/boardMove;
 * when the server refuses it, the board goes back to where it was and the
 * reason is shown. A card click opens the record, as a row click does.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { CalendarClock, UserRound } from 'lucide-vue-next'
import {
  UidBoard,
  UidButton,
  UidErrorState,
  UidIcon,
  UidSkeleton,
  type BoardCard,
  type BoardColumn,
  type BoardLane,
  type BoardMove,
} from '@dskripchenko/ui'
import { getAdminClient } from '../../stores/registry'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { formatLocale, useI18nStore } from '../../stores/i18n'
import { adminToast } from '../../stores/toast'
import { apiErrorMessage } from '../../api/errors'
import type { ResourceKanbanConfig, ResourceKanbanOption } from '../../stores/manifest'

interface Props {
  slug: string
  config: ResourceKanbanConfig
  /** No dragging: the view is read-only or the user may not update. */
  readonly?: boolean
  /** The user may reorder: a drop inside a column sends its position. */
  canReorder?: boolean
}

const props = withDefaults(defineProps<Props>(), { readonly: false, canReorder: false })

/** A card as the board endpoint sends it. */
interface KanbanCard {
  id: string | number
  value: string | number | null
  lane: string | number | null
  title: string
  description: string | null
  tags: string[]
  assignee: string | null
  due: string | null
  overdue: boolean
  locked: boolean
}

interface KanbanColumnData {
  value: string | number
  label: string
  wip: number | null
  total: number
  offset: number
  cards: KanbanCard[]
}

interface BoardResponse {
  columns: KanbanColumnData[]
  lanes: ResourceKanbanOption[] | null
  limit: number
}

const i18n = useI18nStore()
const tr = (s: string): string => i18n.tr(s)
const index = useResourceIndexStore()
const router = useRouter()

/** The lane of cards whose lane attribute is empty. */
const NO_LANE = '__none__'

const columns = ref<KanbanColumnData[]>([])
const lanes = ref<ResourceKanbanOption[] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const busy = ref(false)
const loadingMore = ref<string | null>(null)

function params(): Record<string, unknown> {
  // The board keeps its own order inside a column; the list's sort does not apply.
  const { order: _order, ...rest } = index.queryParams()
  return rest
}

let requestId = 0
async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = null
  try {
    const res = await getAdminClient().post<BoardResponse>(`/${props.slug}/board`, params())
    if (id !== requestId) return
    columns.value = res.columns ?? []
    lanes.value = res.lanes ?? null
  } catch (err) {
    if (id !== requestId) return
    error.value = apiErrorMessage(err, tr('Не удалось загрузить доску.'))
  } finally {
    if (id === requestId) loading.value = false
  }
}

async function loadMore(column: KanbanColumnData): Promise<void> {
  loadingMore.value = String(column.value)
  try {
    const res = await getAdminClient().post<BoardResponse>(`/${props.slug}/board`, {
      ...params(),
      column: column.value,
      offset: column.cards.length,
    })
    const next = res.columns?.[0]
    if (next) {
      const known = new Set(column.cards.map((c) => String(c.id)))
      column.cards.push(...next.cards.filter((c) => !known.has(String(c.id))))
      column.total = next.total
    }
  } catch (err) {
    adminToast.error(apiErrorMessage(err, tr('Не удалось загрузить карточки.')))
  } finally {
    loadingMore.value = null
  }
}

const boardLanes = computed<BoardLane[]>(() => {
  if (lanes.value === null) return []
  const out: BoardLane[] = lanes.value.map((l) => ({ key: l.value, title: l.label }))
  const known = new Set(lanes.value.map((l) => String(l.value)))
  const orphan = columns.value.some((c) => c.cards.some((card) => card.lane === null || !known.has(String(card.lane))))
  if (orphan) out.push({ key: NO_LANE, title: tr('Без дорожки') })
  return out
})

function laneOf(card: KanbanCard): string | number | undefined {
  if (lanes.value === null) return undefined
  const known = lanes.value.some((l) => String(l.value) === String(card.lane))
  return known && card.lane !== null ? card.lane : NO_LANE
}

const boardColumns = computed<BoardColumn[]>(() => columns.value.map((column) => ({
  key: column.value,
  title: column.label,
  wip: column.wip,
  cards: column.cards.map((card) => ({
    key: card.id,
    title: card.title,
    subtitle: card.description ?? undefined,
    tags: card.tags.map((label) => ({ label })),
    lane: laneOf(card),
    // A card Resource::editableForRow() locks stays in its column.
    targets: card.locked ? [column.value] : undefined,
  })),
})))

function dataColumn(key: string | number): KanbanColumnData | undefined {
  return columns.value.find((c) => String(c.value) === String(key))
}

function dataCard(key: string | number): KanbanCard | undefined {
  for (const column of columns.value) {
    const card = column.cards.find((c) => String(c.id) === String(key))
    if (card) return card
  }
  return undefined
}

function isOver(column: KanbanColumnData): boolean {
  return column.wip !== null && column.wip > 0 && column.total > column.wip
}

function snapshot(): KanbanColumnData[] {
  return columns.value.map((c) => ({ ...c, cards: c.cards.map((card) => ({ ...card, tags: [...card.tags] })) }))
}

async function onMove(move: BoardMove): Promise<void> {
  if (props.readonly || busy.value) return
  const from = dataColumn(move.from)
  const to = dataColumn(move.to)
  const card = dataCard(move.card.key)
  if (!from || !to || !card) return

  const before = snapshot()
  // Apply at once: out of the old column, into the new one at the drop point.
  const fromIndex = from.cards.indexOf(card)
  from.cards.splice(fromIndex, 1)
  let at = to.cards.length
  if (move.beforeKey !== null) {
    const i = to.cards.findIndex((c) => String(c.id) === String(move.beforeKey))
    if (i >= 0) at = i
  } else if (move.afterKey !== null) {
    const i = to.cards.findIndex((c) => String(c.id) === String(move.afterKey))
    if (i >= 0) at = i + 1
  }
  to.cards.splice(at, 0, card)
  if (from !== to) {
    from.total = Math.max(0, from.total - 1)
    to.total += 1
  }
  card.value = to.value
  const lane = move.lane !== null && move.lane !== NO_LANE ? move.lane : null
  if (lanes.value !== null && lane !== null) card.lane = lane

  const body: Record<string, unknown> = { ...params(), id: card.id, value: to.value }
  if (lanes.value !== null && lane !== null) body.lane = lane
  if (props.config.saves_position && props.canReorder) body.position = at

  busy.value = true
  try {
    const res = await getAdminClient().post<{ card: KanbanCard }>(`/${props.slug}/boardMove`, body)
    if (res?.card) Object.assign(card, res.card)
  } catch (err) {
    columns.value = before
    adminToast.error(apiErrorMessage(err, tr('Не удалось переместить карточку.')))
  } finally {
    busy.value = false
  }
}

function onOpen(card: BoardCard): void {
  const id = String(card.key)
  for (const name of [`admin.resource.${props.slug}.view`, `admin.resource.${props.slug}.edit`]) {
    if (router.hasRoute(name)) {
      void router.push({ name, params: { id } })
      return
    }
  }
}

function formatDue(due: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?$/.exec(due)
  if (!m) return due
  const date = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4] ?? 0), Number(m[5] ?? 0))
  const options: Intl.DateTimeFormatOptions = m[4] !== undefined
    ? { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }
    : { day: 'numeric', month: 'short' }
  try {
    return date.toLocaleString(formatLocale(), options)
  } catch {
    return due
  }
}

watch(
  () => [props.slug, index.search, JSON.stringify(index.filters)],
  () => void load(),
)
onMounted(() => void load())

defineExpose({ load })
</script>

<template>
  <div class="admin-resource-kanban" data-testid="resource-kanban">
    <div v-if="loading && columns.length === 0" class="admin-resource-kanban__loading">
      <UidSkeleton v-for="i in 3" :key="i" height="160px" />
    </div>
    <UidErrorState
      v-else-if="error !== null"
      :title="tr('Не удалось загрузить данные')"
      :description="error"
    >
      <template #actions>
        <UidButton variant="primary" @click="load">{{ tr('Обновить') }}</UidButton>
      </template>
    </UidErrorState>
    <UidBoard
      v-else
      :columns="boardColumns"
      :lanes="boardLanes"
      :busy="busy"
      :readonly="readonly"
      @move="onMove"
      @open="onOpen"
    >
      <template #column-header="{ column }">
        <template v-if="dataColumn(column.key)">
          <span class="uid-board__title">{{ column.title }}</span>
          <span
            class="admin-resource-kanban__count"
            :class="{ 'admin-resource-kanban__count--over': isOver(dataColumn(column.key)!) }"
            :title="isOver(dataColumn(column.key)!) ? tr('Превышен лимит незавершённой работы') : undefined"
            data-testid="kanban-count"
          >{{ dataColumn(column.key)!.total }}<template v-if="column.wip">/{{ column.wip }}</template></span>
          <UidButton
            v-if="dataColumn(column.key)!.cards.length < dataColumn(column.key)!.total"
            size="sm"
            variant="ghost"
            class="admin-resource-kanban__more"
            data-testid="kanban-more"
            :loading="loadingMore === String(column.key)"
            @click="loadMore(dataColumn(column.key)!)"
          >
            {{ tr('Ещё') }}
          </UidButton>
        </template>
      </template>
      <template #card="{ card }">
        <div class="uid-board__card-title">{{ card.title }}</div>
        <div v-if="card.subtitle" class="uid-board__card-subtitle">{{ card.subtitle }}</div>
        <div v-if="card.tags?.length" class="uid-board__card-tags">
          <span
            v-for="tag in card.tags"
            :key="tag.label"
            class="uid-board__tag uid-board__tag--default"
          >{{ tag.label }}</span>
        </div>
        <div
          v-if="dataCard(card.key)?.assignee || dataCard(card.key)?.due"
          class="admin-resource-kanban__meta"
        >
          <span v-if="dataCard(card.key)?.assignee" class="admin-resource-kanban__assignee">
            <UidIcon :icon="UserRound" :size="12" />
            {{ dataCard(card.key)?.assignee }}
          </span>
          <span
            v-if="dataCard(card.key)?.due"
            class="admin-resource-kanban__due"
            :class="{ 'admin-resource-kanban__due--overdue': dataCard(card.key)?.overdue }"
            :title="dataCard(card.key)?.overdue ? tr('Просрочено') : tr('Срок')"
            data-testid="kanban-due"
          >
            <UidIcon :icon="CalendarClock" :size="12" />
            {{ formatDue(dataCard(card.key)!.due!) }}
          </span>
        </div>
      </template>
    </UidBoard>
  </div>
</template>

<style>
.admin-resource-kanban {
  min-width: 0;
  overflow-x: auto;
  padding-top: var(--uid-space-sm);
}
.admin-resource-kanban__loading {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--uid-space-sm);
}
.admin-resource-kanban__count {
  margin-left: auto;
  color: var(--uid-color-text-secondary);
  font-variant-numeric: tabular-nums;
}
.admin-resource-kanban__count--over {
  color: var(--uid-color-danger);
  font-weight: 600;
}
.admin-resource-kanban__more {
  margin-left: var(--uid-space-xs);
}
.admin-resource-kanban__meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--uid-space-xs);
  margin-top: var(--uid-space-xs);
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-color-text-secondary);
}
.admin-resource-kanban__assignee,
.admin-resource-kanban__due {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.admin-resource-kanban__due--overdue {
  color: var(--uid-color-danger);
  font-weight: 600;
}
</style>

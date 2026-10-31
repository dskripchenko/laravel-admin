<script setup lang="ts">
/**
 * ResourceCalendarView — the calendar view of a resource's index page
 * (Resource::calendar() on the backend), drawn with the kit's UidCalendar.
 *
 * The visible range is loaded on the calendar's `range` event from
 * POST /{slug}/calendar, with the list's search and filters. Dragging or
 * stretching an event is applied at once and saved through
 * POST /{slug}/calendarMove; when the server refuses it, the event goes back
 * and the reason is shown. A click on an event opens the record; a click on
 * an empty day or slot opens the create form with the start prefilled.
 */
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  UidButton,
  UidCalendar,
  UidErrorState,
  type CalendarEvent,
  type CalendarEventMove,
  type CalendarRange,
  type CalendarSlot,
} from '@dskripchenko/ui'
import { getAdminClient } from '../../stores/registry'
import { useResourceIndexStore } from '../../stores/resourceIndex'
import { useI18nStore } from '../../stores/i18n'
import { adminToast } from '../../stores/toast'
import { apiErrorMessage } from '../../api/errors'
import type { ResourceCalendarConfig } from '../../stores/manifest'

type CalendarViewName = ResourceCalendarConfig['initial_view']

interface Props {
  slug: string
  config: ResourceCalendarConfig
  /** No dragging: the view is read-only or the user may not update. */
  readonly?: boolean
  /** The create route, when the user may create; a click on an empty day opens it. */
  createRoute?: string | null
  /**
   * The list parameters — `q`, `filters` — in place of the index page's:
   * set when the calendar is shown outside its page (a dashboard widget), so
   * it neither reads nor follows the index store's search and filters.
   */
  query?: Record<string, unknown> | null
}

const props = withDefaults(defineProps<Props>(), { readonly: false, createRoute: null, query: null })

/** An event as the calendar endpoint sends it. */
interface ResourceEvent {
  id: string | number
  title: string
  /** 'Y-m-d' for an all-day event, 'Y-m-d H:i' otherwise. */
  start: string
  end: string | null
  all_day: boolean
  color: string | null
  editable: boolean
}

/** CalendarView::color() tones as the kit's colour tokens. */
const TONES: Record<string, string> = {
  primary: 'var(--uid-color-primary)',
  success: 'var(--uid-color-success)',
  warning: 'var(--uid-color-warning)',
  danger: 'var(--uid-color-danger)',
  info: 'var(--uid-color-info)',
  neutral: 'var(--uid-color-text-secondary)',
}

const i18n = useI18nStore()
const tr = (s: string): string => i18n.tr(s)
const index = useResourceIndexStore()
const router = useRouter()

const view = ref<CalendarViewName>(props.config.initial_view)
const events = ref<ResourceEvent[]>([])
const range = ref<CalendarRange | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const truncated = ref(false)
const busy = ref(false)

function params(): Record<string, unknown> {
  if (props.query !== null) return { ...props.query }
  const { order: _order, ...rest } = index.queryParams()
  return rest
}

let requestId = 0
async function load(): Promise<void> {
  const r = range.value
  if (r === null) return
  const id = ++requestId
  loading.value = true
  error.value = null
  try {
    const res = await getAdminClient().post<{ events: ResourceEvent[]; truncated?: boolean }>(
      `/${props.slug}/calendar`,
      { ...params(), from: r.start, to: r.end },
    )
    if (id !== requestId) return
    events.value = res.events ?? []
    truncated.value = res.truncated === true
  } catch (err) {
    if (id !== requestId) return
    error.value = apiErrorMessage(err, tr('Не удалось загрузить календарь.'))
  } finally {
    if (id === requestId) loading.value = false
  }
}

function onRange(next: CalendarRange): void {
  const same = range.value !== null && range.value.start === next.start && range.value.end === next.end
  range.value = next
  if (!same) void load()
}

/** A server event as the kit draws it. */
function toCalendarEvent(e: ResourceEvent): CalendarEvent {
  const date = e.start.slice(0, 10)
  // A record editableForRow() locks is not dragged or resized.
  const out: CalendarEvent = { id: e.id, title: e.title, date, editable: e.editable }
  if (e.color) out.color = TONES[e.color] ?? e.color
  if (e.all_day) {
    const last = e.end?.slice(0, 10)
    if (last && last > date) out.endDate = last
    return out
  }
  out.time = e.start.slice(11, 16)
  if (e.end) {
    const endDay = e.end.slice(0, 10)
    if (endDay !== date) out.endDate = endDay
    out.endTime = e.end.slice(11, 16)
  }
  return out
}

const calendarEvents = computed<CalendarEvent[]>(() => events.value.map(toCalendarEvent))

function findEvent(id: unknown): ResourceEvent | undefined {
  return events.value.find((e) => String(e.id) === String(id))
}

/** The start and end a move writes, in the shape calendarMove takes. */
function moveTarget(e: ResourceEvent, m: CalendarEventMove): { start: string; end?: string } {
  if (e.all_day) {
    const out: { start: string; end?: string } = { start: m.date }
    if (e.end !== null) out.end = m.endDate ?? m.date
    return out
  }
  const time = m.time ?? e.start.slice(11, 16)
  const out: { start: string; end?: string } = { start: `${m.date} ${time}` }
  if (e.end !== null) {
    const endTime = m.endTime ?? e.end.slice(11, 16)
    out.end = `${m.endDate ?? m.date} ${endTime}`
  }
  return out
}

async function onEventMove(m: CalendarEventMove): Promise<void> {
  if (props.readonly || busy.value) return
  const e = findEvent(m.event.id)
  if (!e) return
  // The kit does not drag a locked event; a move of one is ignored all the same.
  if (!e.editable) return
  const target = moveTarget(e, m)
  const before = events.value
  // Apply at once; the kit draws the new place before the server answers.
  events.value = events.value.map((x) => (x === e
    ? { ...x, start: target.start, end: target.end ?? x.end }
    : x))
  busy.value = true
  try {
    const res = await getAdminClient().post<{ event: ResourceEvent | null }>(
      `/${props.slug}/calendarMove`,
      { id: e.id, ...target },
    )
    if (res?.event) {
      const saved = res.event
      events.value = events.value.map((x) => (String(x.id) === String(saved.id) ? saved : x))
    }
  } catch (err) {
    events.value = before
    adminToast.error(apiErrorMessage(err, tr('Не удалось перенести событие.')))
  } finally {
    busy.value = false
  }
}

function onEventClick(event: CalendarEvent): void {
  if (event.id === undefined) return
  const id = String(event.id)
  for (const name of [`admin.resource.${props.slug}.view`, `admin.resource.${props.slug}.edit`]) {
    if (router.hasRoute(name)) {
      void router.push({ name, params: { id } })
      return
    }
  }
}

/** Opens the create form with the start column prefilled through its query. */
function create(date: string, time?: string): void {
  if (props.createRoute === null || !router.hasRoute(props.createRoute)) return
  const value = props.config.date_only ? date : `${date} ${time ?? '00:00'}:00`
  void router.push({ name: props.createRoute, query: { [props.config.start]: value } })
}

function onSlotClick(slot: CalendarSlot): void {
  create(slot.date, slot.time)
}

function onDateClick(iso: string): void {
  // The kit reports a day click in the month grid, and also the day picked in
  // the year view or a day header — those navigate, they do not create.
  if (view.value !== 'month') return
  create(iso)
}

watch(
  () => (props.query !== null
    ? [props.slug, JSON.stringify(props.query)]
    : [props.slug, index.search, JSON.stringify(index.filters)]),
  () => void load(),
)

defineExpose({ load })
</script>

<template>
  <div class="admin-resource-calendar" :class="{ 'admin-resource-calendar--loading': loading }" data-testid="resource-calendar">
    <UidErrorState
      v-if="error !== null"
      :title="tr('Не удалось загрузить данные')"
      :description="error"
    >
      <template #actions>
        <UidButton variant="primary" @click="load">{{ tr('Обновить') }}</UidButton>
      </template>
    </UidErrorState>
    <p v-if="truncated" class="admin-resource-calendar__note" role="status">
      {{ tr('Показаны не все события: уточните фильтры.') }}
    </p>
    <UidCalendar
      v-show="error === null"
      v-model:view="view"
      :events="calendarEvents"
      :views="config.views"
      :editable="!readonly"
      :selectable="createRoute !== null"
      @range="onRange"
      @event-click="onEventClick"
      @event-move="onEventMove"
      @slot-click="onSlotClick"
      @date-click="onDateClick"
    />
  </div>
</template>

<style>
.admin-resource-calendar {
  min-width: 0;
  padding-top: var(--uid-space-sm);
}
.admin-resource-calendar--loading .uid-calendar {
  opacity: 0.7;
  transition: opacity 0.15s;
}
.admin-resource-calendar__note {
  margin: 0 0 var(--uid-space-sm);
  font-size: var(--uid-font-size-sm);
  color: var(--uid-color-warning);
}
</style>

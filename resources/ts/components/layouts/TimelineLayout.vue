<script setup lang="ts">
/**
 * TimelineLayout — `Layout::timeline(...)` (core/src/Layout/Timeline.php): a
 * feed of events of different kinds on one time line, newest first.
 *
 * The chips above filter it by kind — «Все N», then each kind with events and
 * its count — and «Только ошибки» keeps the failed ones (`error: true` or the
 * danger tone). An event shows its kind's icon and tone, its time, title, a
 * link (an in-panel address goes through the router; `linkTarget: 'drawer'`
 * opens it in the side panel) and details folded under «Подробнее».
 *
 * The events come from the layout itself or from the state key `source` of
 * the screen's record or form.
 */
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Circle } from 'lucide-vue-next'
import { UidIcon, UidTimeline, UidTimelineItem } from '@dskripchenko/ui'
import { tryUseRecord } from '../infolist/recordContext'
import { tryUseFormState } from '../render/formState'
import { resolveIcon } from '../shell/iconRegistry'
import { openInDrawer, toRouterPath } from '../../composables/useActionRunner'
import { testIdOf } from '../../composables/testId'
import { formatDate, trSafe as tr, tRaw } from '../../stores/i18n'

type Tone = 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger'

interface TimelineType {
  key: string
  label: string
  icon?: string | null
  tone?: string | null
}

export interface TimelineEvent {
  id?: string | number
  type?: string | null
  title: string
  time?: string | null
  details?: string | Array<{ label: string; value: unknown }> | null
  tone?: string | null
  icon?: string | null
  error?: boolean
  url?: string | null
  linkLabel?: string | null
  linkTarget?: string | null
}

interface Props {
  events?: TimelineEvent[] | null
  source?: string | null
  types?: TimelineType[] | null
  title?: string | null
  emptyText?: string | null
  limit?: number | null
  filters?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  events: null,
  source: null,
  types: null,
  title: null,
  emptyText: null,
  limit: null,
  filters: true,
})

const router = useRouter()
const record = tryUseRecord()
const form = tryUseFormState()

const TONES: Tone[] = ['neutral', 'primary', 'info', 'success', 'warning', 'danger']

const all = computed<TimelineEvent[]>(() => {
  let list: unknown = props.events
  if (list === null && props.source) {
    list = (record ?? form?.state ?? {})[props.source]
  }
  return Array.isArray(list)
    ? list.filter((e): e is TimelineEvent => !!e && typeof e === 'object' && typeof (e as TimelineEvent).title === 'string')
    : []
})

const typeOf = (key: string | null | undefined): TimelineType | undefined => (props.types ?? []).find((t) => t.key === key)

function toneOf(e: TimelineEvent): Tone {
  const tone = e.tone ?? typeOf(e.type)?.tone ?? (e.error ? 'danger' : 'neutral')
  return (TONES as string[]).includes(tone) ? (tone as Tone) : 'neutral'
}
function isError(e: TimelineEvent): boolean {
  return e.error === true || toneOf(e) === 'danger'
}
function iconOf(e: TimelineEvent) {
  return resolveIcon(e.icon ?? typeOf(e.type)?.icon ?? null) ?? Circle
}

/** The chips: the declared kinds that have events, in their order, then the undeclared ones. */
const chips = computed(() => {
  const counts = new Map<string, number>()
  for (const e of all.value) {
    const k = e.type ?? ''
    if (k !== '') counts.set(k, (counts.get(k) ?? 0) + 1)
  }
  const out: Array<{ key: string; label: string; count: number }> = []
  for (const t of props.types ?? []) {
    const n = counts.get(t.key)
    if (n) out.push({ key: t.key, label: t.label, count: n })
    counts.delete(t.key)
  }
  for (const [key, count] of counts) out.push({ key, label: key, count })
  return out
})
const errorCount = computed<number>(() => all.value.filter(isError).length)

const activeType = ref<string | null>(null)
const onlyErrors = ref(false)
const showAll = ref(false)
const openDetails = ref<Set<number>>(new Set())

const filtered = computed<TimelineEvent[]>(() =>
  all.value.filter((e) => (activeType.value === null || e.type === activeType.value) && (!onlyErrors.value || isError(e))),
)
const visible = computed<TimelineEvent[]>(() =>
  props.limit && !showAll.value ? filtered.value.slice(0, props.limit) : filtered.value,
)

function timeText(time: string | null | undefined): string {
  if (!time) return ''
  if (!/^\d{4}-\d{2}-\d{2}/.test(time)) return time
  const d = new Date(time)
  if (Number.isNaN(d.getTime())) return time
  return formatDate(d, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function hasDetails(e: TimelineEvent): boolean {
  return typeof e.details === 'string' ? e.details !== '' : Array.isArray(e.details) && e.details.length > 0
}
function toggleDetails(i: number): void {
  const next = new Set(openDetails.value)
  if (next.has(i)) next.delete(i)
  else next.add(i)
  openDetails.value = next
}

function follow(e: TimelineEvent, event: MouseEvent): void {
  if (!e.url || event.ctrlKey || event.metaKey || event.shiftKey) return
  if (e.linkTarget === 'drawer') {
    event.preventDefault()
    openInDrawer(e.url, { title: e.linkLabel ?? e.title })
    return
  }
  const path = toRouterPath(router, e.url)
  if (path !== null) {
    event.preventDefault()
    void router.push(path).catch(() => undefined)
  }
}
</script>

<template>
  <section class="admin-timeline" data-testid="timeline">
    <h3 v-if="title" class="admin-timeline__title">{{ title }}</h3>

    <div v-if="filters && all.length > 0" class="admin-timeline__chips" role="toolbar">
      <button
        type="button"
        class="admin-timeline__chip"
        :class="{ 'admin-timeline__chip--active': activeType === null }"
        data-testid="timeline-chip-all"
        @click="activeType = null"
      >{{ tRaw('Все :n', { n: all.length }) }}</button>
      <button
        v-for="chip in chips"
        :key="chip.key"
        type="button"
        class="admin-timeline__chip"
        :class="{ 'admin-timeline__chip--active': activeType === chip.key }"
        :data-testid="testIdOf('timeline-chip', chip.key)"
        @click="activeType = activeType === chip.key ? null : chip.key"
      >{{ chip.label }} <span class="admin-timeline__chip-count">{{ chip.count }}</span></button>
      <button
        v-if="errorCount > 0"
        type="button"
        class="admin-timeline__chip admin-timeline__chip--errors"
        :class="{ 'admin-timeline__chip--active': onlyErrors }"
        :aria-pressed="onlyErrors"
        data-testid="timeline-only-errors"
        @click="onlyErrors = !onlyErrors"
      >{{ tr('Только ошибки') }} <span class="admin-timeline__chip-count">{{ errorCount }}</span></button>
    </div>

    <p v-if="visible.length === 0" class="admin-timeline__empty" data-testid="timeline-empty">
      {{ all.length === 0 ? (emptyText ?? tr('Событий нет')) : tr('Под условия не подходит ни одно событие') }}
    </p>

    <UidTimeline v-else>
      <UidTimelineItem
        v-for="(e, i) in visible"
        :key="e.id ?? i"
        :tone="toneOf(e)"
        :time="timeText(e.time)"
        :data-testid="testIdOf('timeline-event', e.id ?? i)"
        :data-type="e.type ?? ''"
      >
        <template #icon><UidIcon :icon="iconOf(e)" :size="14" /></template>
        <div class="admin-timeline__head">
          <span class="admin-timeline__event-title">{{ e.title }}</span>
          <span v-if="typeOf(e.type)" class="admin-timeline__type">{{ typeOf(e.type)!.label }}</span>
        </div>
        <div class="admin-timeline__links">
          <a
            v-if="e.url"
            class="admin-timeline__link"
            :href="e.url"
            data-testid="timeline-link"
            @click="follow(e, $event)"
          >{{ e.linkLabel ?? tr('Открыть') }}</a>
          <button
            v-if="hasDetails(e)"
            type="button"
            class="admin-timeline__more"
            :aria-expanded="openDetails.has(i)"
            data-testid="timeline-details-toggle"
            @click="toggleDetails(i)"
          >{{ openDetails.has(i) ? tr('Скрыть') : tr('Подробнее') }}</button>
        </div>
        <div v-if="hasDetails(e) && openDetails.has(i)" class="admin-timeline__details" data-testid="timeline-details">
          <template v-if="typeof e.details === 'string'">{{ e.details }}</template>
          <dl v-else class="admin-timeline__dl">
            <template v-for="(line, j) in e.details" :key="j">
              <dt>{{ line.label }}</dt>
              <dd>{{ line.value }}</dd>
            </template>
          </dl>
        </div>
      </UidTimelineItem>
    </UidTimeline>

    <button
      v-if="limit && !showAll && filtered.length > limit"
      type="button"
      class="admin-timeline__more"
      data-testid="timeline-show-all"
      @click="showAll = true"
    >{{ tRaw('Показать ещё :n', { n: filtered.length - limit }) }}</button>
  </section>
</template>

<style>
.admin-timeline {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
  min-width: 0;
}
.admin-timeline__title {
  margin: 0;
  font-size: var(--uid-font-size-md, 15px);
}
.admin-timeline__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.admin-timeline__chip {
  border: 1px solid var(--uid-border-default, rgba(0, 0, 0, 0.12));
  border-radius: 999px;
  padding: 2px 10px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: var(--uid-font-size-sm, 13px);
  cursor: pointer;
}
.admin-timeline__chip--active {
  background: var(--uid-color-primary-subtle, rgba(0, 0, 0, 0.06));
  border-color: var(--uid-color-primary, rgba(0, 0, 0, 0.3));
  font-weight: var(--uid-font-weight-medium, 500);
}
.admin-timeline__chip--errors.admin-timeline__chip--active {
  color: var(--uid-color-danger-text, #c62828);
}
.admin-timeline__chip-count {
  color: var(--uid-text-secondary);
}
.admin-timeline__empty {
  margin: 0;
  color: var(--uid-text-secondary);
}
.admin-timeline__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
}
.admin-timeline__event-title {
  font-weight: var(--uid-font-weight-medium, 500);
}
.admin-timeline__type {
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-timeline__links {
  display: flex;
  gap: 12px;
  font-size: var(--uid-font-size-sm, 13px);
}
.admin-timeline__link {
  color: var(--uid-color-primary, inherit);
}
.admin-timeline__more {
  align-self: flex-start;
  padding: 0;
  border: 0;
  background: none;
  color: var(--uid-color-primary, inherit);
  font: inherit;
  font-size: var(--uid-font-size-sm, 13px);
  cursor: pointer;
}
.admin-timeline__details {
  margin-top: 4px;
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-text-secondary);
  white-space: pre-wrap;
}
.admin-timeline__dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 12px;
  margin: 0;
}
.admin-timeline__dl dd {
  margin: 0;
}
</style>

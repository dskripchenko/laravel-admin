<script setup lang="ts">
/**
 * The calendar layout — `Layout::calendar(...)`: an event calendar written
 * as markup, drawn by the kit's UidEventCalendar — day, week, month, year
 * and agenda views, repeats.
 *
 * The markup is inline (`content`), a key of the screen's state (`source`)
 * or fetched from `url`; `view`, `date` and `views` set what is shown first
 * and what is offered. With `saveMethod` the calendar is editable: each
 * change is sent to that screen method as the new markup, and a refusal
 * rolls it back with a toast. `lockMethod` shows the lock button; `locked` /
 * `lockedSource` make it read-only. See useMarkupEditing.
 */
import { UidEventCalendar } from '@dskripchenko/ui'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { cssHeight, useDocumentSource } from '../documents/useDocumentSource'
import { useMarkupEditing } from '../documents/useMarkupEditing'

type CalendarView = 'day' | 'week' | 'month' | 'year' | 'agenda'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  height?: string | number | null
  view?: CalendarView | null
  date?: string | null
  views?: CalendarView[] | null
  editable?: boolean
  saveMethod?: string | null
  lockable?: boolean
  lockMethod?: string | null
  locked?: boolean
  lockedSource?: string | null
}>(), {
  title: null,
  content: null,
  source: null,
  url: null,
  height: null,
  view: null,
  date: null,
  views: null,
  editable: false,
  saveMethod: null,
  lockable: false,
  lockMethod: null,
  locked: false,
  lockedSource: null,
})

const { text, loading, error } = useDocumentSource(props)
const { markup, canEdit, canLock, isLocked, onChange, onLock } = useMarkupEditing(props, text)
</script>

<template>
  <DocumentFrame
    kind="calendar"
    :title="title"
    :height="cssHeight(height)"
    :loading="loading || text === null"
    :error="error"
    scroll
  >
    <UidEventCalendar
      :source="markup"
      :view="view ?? ''"
      :date="date ?? ''"
      :views="views ?? undefined"
      :editable="canEdit"
      :lockable="canLock"
      :locked="isLocked"
      @change="onChange"
      @lock="onLock"
    />
  </DocumentFrame>
</template>

<script setup lang="ts">
/**
 * MarkupEntry — a stored kanban board or event calendar, read-only: the
 * backend's Infolist\KanbanEntry / CalendarEntry and the view of a
 * Field\Kanban / Field\Calendar. The kanban and calendar layouts draw it,
 * without editing and without a lock. KanbanEntry and CalendarEntry are this
 * component with `kind` set.
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { toText } from '../documents/useDocumentSource'
import KanbanLayout from '../layouts/KanbanLayout.vue'
import CalendarLayout from '../layouts/CalendarLayout.vue'

type CalendarView = 'day' | 'week' | 'month' | 'year' | 'agenda'

defineOptions({ inheritAttrs: false })

interface Props {
  name?: string
  value?: unknown
  kind: 'kanban' | 'calendar'
  height?: number | string | null
  view?: CalendarView | null
  date?: string | null
  views?: CalendarView[] | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  height: null,
  view: null,
  date: null,
  views: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const markup = computed(() => toText(raw.value))
</script>

<template>
  <span v-if="markup.trim() === ''" class="admin-infolist-text">{{ placeholder }}</span>
  <KanbanLayout v-else-if="kind === 'kanban'" :content="markup" :height="height" />
  <CalendarLayout
    v-else
   
    :content="markup"
    :height="height"
    :view="view"
    :date="date"
    :views="views"
  />
</template>

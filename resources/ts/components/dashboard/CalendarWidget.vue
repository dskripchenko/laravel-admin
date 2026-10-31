<script setup lang="ts">
/**
 * The calendar widget — the backend's CalendarWidget, or a user-added one.
 *
 * - `mode: 'resource'` — the resource's calendar view (Resource::calendar()),
 *   the index page's ResourceCalendarView with the widget's preset search
 *   and filters instead of the page's: the events come from
 *   POST /{slug}/calendar. Read-only unless `editable` and the user may
 *   update the resource; a click on an empty day creates nothing here.
 * - `mode: 'markup'` — a calendar written as markup, the kit's
 *   UidEventCalendar, read-only.
 *
 * `initialView` — the agenda or the week fit a widget — replaces the view's
 * own first view; `views` the ones offered. The calendar scrolls inside the
 * widget.
 */
import { computed } from 'vue'
import { UidAlert } from '@dskripchenko/ui'
import CalendarLayout from '../layouts/CalendarLayout.vue'
import ResourceCalendarView from '../resource/ResourceCalendarView.vue'
import WidgetFrame from './WidgetFrame.vue'
import { useResourceWidget } from './resourceWidget'
import type { ResourceCalendarConfig } from '../../stores/manifest'
import { trSafe as tr } from '../../stores/i18n'

type CalendarView = ResourceCalendarConfig['initial_view']

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  mode?: 'resource' | 'markup' | null
  resource?: string | null
  search?: string | null
  filters?: Record<string, unknown> | unknown[] | null
  editable?: boolean | null
  content?: string | null
  initialView?: CalendarView | null
  date?: string | null
  views?: CalendarView[] | null
}>(), {
  title: null,
  mode: null,
  resource: null,
  search: null,
  filters: null,
  editable: false,
  content: null,
  initialView: null,
  date: null,
  views: null,
})

const byResource = computed(() => props.mode === 'resource' || (props.mode !== 'markup' && !!props.resource))
const { meta, query, canUpdate } = useResourceWidget(props, 'calendar')

/** The resource's calendar config with the widget's first view and views. */
const config = computed<ResourceCalendarConfig | null>(() => {
  const base = meta.value?.calendar
  if (!base) return null
  const initial = props.initialView ?? base.initial_view
  let views = props.views && props.views.length > 0 ? [...props.views] : [...base.views]
  if (!views.includes(initial)) views = [initial, ...views]
  return { ...base, initial_view: initial, views }
})
</script>

<template>
  <WidgetFrame kind="calendar" :title="title">
    <template v-if="byResource">
      <ResourceCalendarView
        v-if="meta && config"
        :slug="meta.slug"
        :config="config"
        :readonly="!canUpdate"
        :query="query"
      />
      <UidAlert v-else variant="info" class="admin-widget-frame__notice">
        {{ tr('Календарь недоступен: ресурса нет, у него нет вида «календарь» или у вас нет права его смотреть.') }}
      </UidAlert>
    </template>
    <CalendarLayout
      v-else
      :content="content ?? ''"
      :view="initialView"
      :date="date"
      :views="views"
      height="100%"
    />
  </WidgetFrame>
</template>

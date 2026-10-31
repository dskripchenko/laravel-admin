<script setup lang="ts">
/**
 * MarkupField — the backend's Field\Kanban and Field\Calendar: a board or an
 * event calendar written as markup, edited in place by the kit's UidKanban
 * or UidEventCalendar. The value is the markup, a string; each change the
 * user makes is written into the form state and submitted with the form.
 *
 * There is no lock: `readonly` / `disabled` make the component read-only.
 * For the calendar, `view`, `date` and `views` set what is shown first and
 * what is offered. KanbanField and CalendarField are this component with
 * `kind` set.
 */
import { computed } from 'vue'
import { UidEventCalendar, UidFormField, UidKanban } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { cssHeight } from '../documents/useDocumentSource'

type CalendarView = 'day' | 'week' | 'month' | 'year' | 'agenda'

defineOptions({ inheritAttrs: false })

interface Props {
  name: string
  kind: 'kanban' | 'calendar'
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  height?: number | string | null
  view?: CalendarView | null
  date?: string | null
  views?: CalendarView[] | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  height: null,
  view: null,
  date: null,
  views: null,
})

const form = useFormState()

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const markup = computed<string>(() => {
  const v = form.getField(props.name)
  return typeof v === 'string' ? v : ''
})

const editable = computed(() => !props.readonly && !props.disabled)

function onChange(next: string): void {
  if (editable.value && next !== markup.value) form.setField(props.name, next)
}

const height = computed(() => cssHeight(props.height))
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div
      class="admin-editor-field admin-editor-field--markup"
      :class="[`admin-editor-field--${kind}`, { 'admin-editor-field--invalid': !!errorMsg, 'admin-editor-field--fixed': !!height }]"
      :style="height ? { height } : undefined"
    >
      <UidKanban
        v-if="kind === 'kanban'"
        :source="markup"
        :editable="editable"
        @change="onChange"
      />
      <UidEventCalendar
        v-else
        :source="markup"
        :view="view ?? ''"
        :date="date ?? ''"
        :views="views ?? undefined"
        :editable="editable"
        @change="onChange"
      />
    </div>
  </UidFormField>
</template>

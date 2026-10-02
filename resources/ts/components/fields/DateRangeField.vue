<script setup lang="ts">
/**
 * DateRangeField — the backend's Field\DateRange over UidDateRangePicker.
 *
 * The state is `{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}` (null when empty);
 * a `[from, to]` pair or `{start, end}` from a host is read as well.
 * `->presets([...])` adds shortcut buttons: today, yesterday, last_7_days,
 * last_30_days, last_90_days, this_week, this_month, last_month, this_year.
 */
import { computed } from 'vue'
import { UidButton, UidDateRangePicker, UidFormField } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { trSafe as tr } from '../../stores/i18n'
import { readDateRange, type DateRangeValue } from './support/dateRange'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  min?: string | null
  max?: string | null
  presets?: string[] | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  readonly: false,
  min: null,
  max: null,
  presets: null,
})

type Range = DateRangeValue

const form = useFormState()
const value = computed<Range>(() => readDateRange(form.getField(props.name)))
const errorMsg = computed<string | undefined>(
  () => form.errors[props.name]?.[0] ?? form.errors[`${props.name}.from`]?.[0] ?? form.errors[`${props.name}.to`]?.[0],
)
const isLocked = computed<boolean>(() => props.disabled || props.readonly)

// The picker emits null when it is cleared.
function onUpdate(next: Range | null): void {
  if (props.readonly) return
  form.setField(
    props.name,
    next === null || (next.start === null && next.end === null) ? null : { from: next.start, to: next.end },
  )
}

const iso = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

function presetRange(key: string, now = new Date()): Range | null {
  const y = now.getFullYear()
  const m = now.getMonth()
  const d = now.getDate()
  const back = (days: number): Range => ({ start: iso(new Date(y, m, d - days + 1)), end: iso(now) })
  switch (key) {
    case 'today': return { start: iso(now), end: iso(now) }
    case 'yesterday': { const t = iso(new Date(y, m, d - 1)); return { start: t, end: t } }
    case 'last_7_days': return back(7)
    case 'last_30_days': return back(30)
    case 'last_90_days': return back(90)
    case 'this_week': {
      const offset = (now.getDay() + 6) % 7
      return { start: iso(new Date(y, m, d - offset)), end: iso(now) }
    }
    case 'this_month': return { start: iso(new Date(y, m, 1)), end: iso(now) }
    case 'last_month': return { start: iso(new Date(y, m - 1, 1)), end: iso(new Date(y, m, 0)) }
    case 'this_year': return { start: iso(new Date(y, 0, 1)), end: iso(now) }
    default: return null
  }
}

const PRESET_LABELS: Record<string, string> = {
  today: tr('Сегодня'),
  yesterday: tr('Вчера'),
  last_7_days: tr('7 дней'),
  last_30_days: tr('30 дней'),
  last_90_days: tr('90 дней'),
  this_week: tr('Эта неделя'),
  this_month: tr('Этот месяц'),
  last_month: tr('Прошлый месяц'),
  this_year: tr('Этот год'),
}

const presetButtons = computed<Array<{ key: string; label: string }>>(() =>
  (props.presets ?? [])
    .filter((k) => presetRange(k) !== null)
    .map((k) => ({ key: k, label: PRESET_LABELS[k] ?? k })),
)

function applyPreset(key: string): void {
  const r = presetRange(key)
  if (r) onUpdate(r)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div class="admin-date-range-field">
      <UidDateRangePicker
        :model-value="value"
        :min="min ?? undefined"
        :max="max ?? undefined"
        :placeholder="placeholder ?? undefined"
        :disabled="isLocked"
        :clearable="!required"
        @update:model-value="onUpdate"
      />
      <div v-if="presetButtons.length && !isLocked" class="admin-date-range-field__presets">
        <UidButton
          v-for="p in presetButtons"
          :key="p.key"
          variant="ghost"
          size="sm"
          :data-preset="p.key"
          @click="applyPreset(p.key)"
        >
          {{ p.label }}
        </UidButton>
      </div>
    </div>
  </UidFormField>
</template>

<style>
.admin-date-range-field {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 6px);
}
.admin-date-range-field__presets {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-2xs, 2px);
}
</style>

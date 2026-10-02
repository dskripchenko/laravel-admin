<script setup lang="ts">
/**
 * DateField — the backend's Field\DatePicker over UidDatePicker.
 *
 * With `withTime()` it is a date and a time side by side (UidDatePicker and
 * UidTimePicker), stored as one string in the field's `format`: 'Y-m-d H:i:s'
 * by default, 'Y-m-d H:i' without seconds, 'Y-m-d\TH:i:s' with a `T`.
 */
import { computed, ref, watch } from 'vue'
import { UidDatePicker, UidFormField, UidTimePicker } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { formatHasSeconds, joinDateTime, splitDateTime } from './support/dateTime'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /**
   * The input's type: 'date' by default, or 'datetime-local' or 'time'.
   * 'datetime-local' turns the time on, like `withTime`.
   */
  inputType?: 'date' | 'datetime-local' | 'time'
  min?: string | null
  max?: string | null
  disabled?: boolean
  readonly?: boolean
  /** DatePicker::withTime(): a time picker next to the date. */
  withTime?: boolean
  /** The PHP format the value is stored in. */
  format?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  inputType: 'date',
  min: null,
  max: null,
  required: false,
  disabled: false,
  readonly: false,
  withTime: false,
  format: null,
})

const form = useFormState()
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const timed = computed<boolean>(() => props.withTime || props.inputType === 'datetime-local')

const parts = computed(() => splitDateTime(form.getField(props.name)))
const dateValue = computed<string | null>(() => {
  if (timed.value) return parts.value.date
  const v = form.getField(props.name)
  return v === null || v === undefined || v === '' ? null : String(v)
})

/** A time picked before the date: kept until the date arrives. */
const pendingTime = ref<string | null>(null)
const timeValue = computed<string | null>(() => parts.value.time ?? pendingTime.value)
watch(
  () => parts.value.date,
  (date) => {
    if (date) pendingTime.value = null
  },
)

function onDate(next: string | null): void {
  if (props.readonly) return
  if (!timed.value) {
    form.setField(props.name, next)
    return
  }
  form.setField(props.name, joinDateTime(next, timeValue.value, props.format))
}

function onTime(next: string | null): void {
  if (props.readonly) return
  if (!parts.value.date) {
    pendingTime.value = next
    return
  }
  form.setField(props.name, joinDateTime(parts.value.date, next, props.format))
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
    <div v-if="timed" class="admin-date-time-field">
      <UidDatePicker
        :model-value="dateValue"
        :min="min ?? undefined"
        :max="max ?? undefined"
        :disabled="disabled || readonly"
        :name="name"
        @update:model-value="onDate"
      />
      <UidTimePicker
        :model-value="timeValue"
        :with-seconds="formatHasSeconds(format)"
        :disabled="disabled || readonly"
        :clearable="false"
        @update:model-value="onTime"
      />
    </div>
    <UidDatePicker
      v-else
      :model-value="dateValue"
      :min="min ?? undefined"
      :max="max ?? undefined"
      :disabled="disabled || readonly"
      :name="name"
      @update:model-value="onDate"
    />
  </UidFormField>
</template>

<style scoped>
.admin-date-time-field {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-sm);
  align-items: center;
}
.admin-date-time-field > :first-child {
  flex: 1 1 12rem;
  min-width: 0;
}
.admin-date-time-field > :last-child {
  flex: 0 1 9rem;
  min-width: 0;
}
</style>

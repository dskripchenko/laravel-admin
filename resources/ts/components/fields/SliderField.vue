<script setup lang="ts">
/**
 * SliderField — the backend's Field\Slider over UidSlider: min, max, step and
 * the current value. `->marks([value => label])` are drawn as ticks under the
 * track, placed by their value. The visible caption is UidFormField's alone
 * (UidSlider would draw it a second time above the track); the handle gets
 * the same text as its accessible name through `ariaLabel`.
 */
import { computed } from 'vue'
import { UidFormField, UidSlider } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  min?: number
  max?: number
  step?: number
  marks?: Record<string, string> | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  disabled: false,
  readonly: false,
  min: 0,
  max: 100,
  step: 1,
  marks: null,
})

const form = useFormState()
const value = computed<number>(() => {
  const v = Number(form.getField(props.name))
  return Number.isFinite(v) ? v : props.min
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const markList = computed<Array<{ at: number; label: string }>>(() => {
  const span = props.max - props.min
  if (!props.marks || span <= 0) return []
  return Object.entries(props.marks)
    .map(([v, label]) => ({ v: Number(v), label: String(label) }))
    .filter((m) => Number.isFinite(m.v) && m.v >= props.min && m.v <= props.max)
    .map((m) => ({ at: ((m.v - props.min) / span) * 100, label: m.label }))
})

function onUpdate(next: number): void {
  if (props.readonly) return
  form.setField(props.name, next)
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
    <div class="admin-slider-field">
      <UidSlider
        :model-value="value"
        :min="min"
        :max="max"
        :step="step"
        :disabled="disabled || readonly"
        :aria-label="label || name"
        show-value
        @update:model-value="onUpdate"
      />
      <div v-if="markList.length" class="admin-slider-field__marks" aria-hidden="true">
        <span
          v-for="m in markList"
          :key="m.at"
          class="admin-slider-field__mark"
          :style="{ left: `${m.at}%` }"
        >{{ m.label }}</span>
      </div>
    </div>
  </UidFormField>
</template>

<style>
.admin-slider-field__marks {
  position: relative;
  height: 1.5em;
  margin-top: var(--uid-space-2xs, 2px);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-slider-field__mark {
  position: absolute;
  transform: translateX(-50%);
  white-space: nowrap;
}
.admin-slider-field__mark:first-child { transform: none; }
.admin-slider-field__mark:last-child:not(:first-child) { transform: translateX(-100%); }
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { UidCard, UidGauge } from '@dskripchenko/ui'
import type { GaugeTone, GaugeRange } from '@dskripchenko/ui'
import { toneColor } from './toneColor'

type SemanticTone = 'neutral' | 'positive' | 'warning' | 'negative'

interface Props {
  title?: string
  value?: number
  min?: number
  max?: number
  size?: number
  ranges?: GaugeRange[]
  /**
   * The backend's GaugeWidget::data() returns `thresholds`, which matches
   * UidGauge.ranges ({from, to, color}) structurally. Both names are accepted.
   * A zone's colour is a tone name (success, warning, danger, info, primary,
   * neutral, or green, amber, red…), mapped onto the kit's tokens; a CSS
   * colour passes through.
   */
  thresholds?: GaugeRange[]
  unit?: string
  tone?: SemanticTone
  color?: string
  label?: string
  suffix?: string
  /**
   * The decimals shown, GaugeWidget::precision(). Unset, a whole value shows
   * none and a fractional one shows up to two, so 82.6 is not rounded to 83.
   */
  precision?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  value: 0,
  min: 0,
  max: 100,
  size: 220,
  ranges: () => [],
  thresholds: () => [],
  unit: '',
  tone: 'neutral',
  color: undefined,
  label: '',
  suffix: '',
  precision: null,
})

const TONE_MAP: Record<SemanticTone, GaugeTone> = {
  neutral: 'primary',
  positive: 'success',
  warning: 'warning',
  negative: 'danger',
}

const uidTone = computed<GaugeTone>(() => TONE_MAP[props.tone])

const resolvedRanges = computed<GaugeRange[]>(() =>
  (props.ranges.length > 0 ? props.ranges : props.thresholds).map((r) => ({
    ...r,
    color: toneColor(r.color) || undefined,
  })),
)

/** The decimals of a number as written, up to two: 82.6 → 1, 83 → 0. */
function decimalsOf(n: number): number {
  if (!Number.isFinite(n) || Number.isInteger(n)) return 0
  const fraction = String(n).split('.')[1] ?? ''
  return Math.min(2, fraction.length)
}

const resolvedPrecision = computed<number>(() =>
  typeof props.precision === 'number' && props.precision >= 0
    ? Math.min(20, Math.floor(props.precision))
    : decimalsOf(Number(props.value)),
)
const resolvedSuffix = computed<string>(
  () => props.suffix || props.unit || '',
)
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div class="admin-gauge-widget__body">
      <UidGauge
        :value="value"
        :min="min"
        :max="max"
        :size="size"
        :ranges="resolvedRanges"
        :tone="uidTone"
        :color="toneColor(color) || undefined"
        :label="label"
        :suffix="resolvedSuffix"
        :precision="resolvedPrecision"
      />
    </div>
  </UidCard>
</template>

<style>
.admin-gauge-widget__body {
  flex: 1 1 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
}
</style>

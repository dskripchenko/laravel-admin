<script setup lang="ts">
import { computed } from 'vue'
import { UidCard, UidGauge } from '@dskripchenko/ui'
import type { GaugeTone, GaugeRange } from '@dskripchenko/ui'

type SemanticTone = 'neutral' | 'positive' | 'warning' | 'negative'

interface Props {
  title?: string
  value?: number
  min?: number
  max?: number
  size?: number
  ranges?: GaugeRange[]
  /**
   * Backend GaugeWidget::data() отдаёт `thresholds` — структурно совпадает
   * с UidGauge.ranges ({from, to, color}). Принимаем оба имени.
   */
  thresholds?: GaugeRange[]
  unit?: string
  tone?: SemanticTone
  color?: string
  label?: string
  suffix?: string
  precision?: number
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
  precision: 0,
})

const TONE_MAP: Record<SemanticTone, GaugeTone> = {
  neutral: 'primary',
  positive: 'success',
  warning: 'warning',
  negative: 'danger',
}

const uidTone = computed<GaugeTone>(() => TONE_MAP[props.tone])

const resolvedRanges = computed<GaugeRange[]>(
  () => (props.ranges.length > 0 ? props.ranges : props.thresholds),
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
        :color="color"
        :label="label"
        :suffix="resolvedSuffix"
        :precision="precision"
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

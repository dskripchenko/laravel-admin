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
  size: 160,
  ranges: () => [],
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
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div style="display:flex; justify-content:center;">
      <UidGauge
        :value="value"
        :min="min"
        :max="max"
        :size="size"
        :ranges="ranges"
        :tone="uidTone"
        :color="color"
        :label="label"
        :suffix="suffix"
        :precision="precision"
      />
    </div>
  </UidCard>
</template>

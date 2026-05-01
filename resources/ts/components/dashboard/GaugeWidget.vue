<script setup lang="ts">
import { UidCard, UidGauge } from '@dskripchenko/ui'

interface GaugeRange {
  from: number
  to: number
  color?: string
}

interface Props {
  title?: string
  value?: number
  min?: number
  max?: number
  size?: number
  ranges?: GaugeRange[]
  tone?: 'neutral' | 'positive' | 'warning' | 'negative'
  color?: string
  label?: string
  suffix?: string
  precision?: number
}

withDefaults(defineProps<Props>(), {
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
        :ranges="ranges as never"
        :tone="tone as never"
        :color="color"
        :label="label"
        :suffix="suffix"
        :precision="precision"
      />
    </div>
  </UidCard>
</template>

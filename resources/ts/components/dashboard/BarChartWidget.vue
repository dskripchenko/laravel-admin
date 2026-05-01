<script setup lang="ts">
/**
 * BarChartWidget — простой SVG-bar chart без внешних зависимостей.
 *
 * Manifest:
 *   { type: 'bar-chart', title: '30 дней',
 *     data: [{ label: '01', value: 12 }, ...],
 *     accent: 'var(--uid-accent)' }
 */
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'

interface Datum {
  label: string
  value: number
}

interface Props {
  title?: string
  description?: string
  data: Datum[]
  accent?: string
  height?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  accent: 'var(--uid-accent)',
  height: 200,
})

const maxValue = computed(() => Math.max(1, ...props.data.map((d) => d.value)))

const bars = computed(() => {
  const n = Math.max(1, props.data.length)
  const barWidth = 100 / n
  return props.data.map((d, i) => ({
    label: d.label,
    value: d.value,
    x: i * barWidth + barWidth * 0.1,
    width: barWidth * 0.8,
    heightPct: (d.value / maxValue.value) * 100,
  }))
})
</script>

<template>
  <UidCard padding="md" class="admin-widget">
    <header v-if="title || description" class="admin-widget__hd">
      <h3 v-if="title" class="admin-widget__title">{{ title }}</h3>
      <p v-if="description" class="admin-widget__desc">{{ description }}</p>
    </header>
    <svg
      class="admin-widget-bar-chart"
      :viewBox="`0 0 100 ${height}`"
      preserveAspectRatio="none"
      :height="height"
      width="100%"
      role="img"
      :aria-label="title || 'Bar chart'"
    >
      <rect
        v-for="(bar, idx) in bars"
        :key="idx"
        :x="bar.x"
        :y="height - (bar.heightPct / 100) * height"
        :width="bar.width"
        :height="(bar.heightPct / 100) * height"
        :fill="accent"
        rx="1"
        :data-label="bar.label"
        :data-value="bar.value"
      >
        <title>{{ bar.label }}: {{ bar.value }}</title>
      </rect>
    </svg>
  </UidCard>
</template>

<style>
.admin-widget__hd { margin-bottom: var(--uid-space-sm); }
.admin-widget__title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-widget__desc {
  margin: var(--uid-space-2xs) 0 0;
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
.admin-widget-bar-chart {
  display: block;
  width: 100%;
}
</style>

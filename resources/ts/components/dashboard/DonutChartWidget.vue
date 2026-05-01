<script setup lang="ts">
/**
 * DonutChartWidget — SVG donut + legend.
 *
 * Manifest:
 *   { type: 'donut-chart', title: 'Status',
 *     data: [{ label: 'Published', value: 156, color: '...' }, ...] }
 */
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'

interface Slice {
  label: string
  value: number
  color?: string
}

interface Props {
  title?: string
  data: Slice[]
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
})

const PALETTE = [
  'var(--uid-color-teal-500, #14b8a6)',
  'var(--uid-color-zinc-700, #3f3f46)',
  'var(--uid-color-amber-400, #fbbf24)',
  'var(--uid-color-rose-400, #fb7185)',
  'var(--uid-color-blue-400, #60a5fa)',
]

const total = computed(() => props.data.reduce((s, x) => s + x.value, 0) || 1)

const slices = computed(() => {
  let cumulative = 0
  return props.data.map((s, idx) => {
    const startAngle = (cumulative / total.value) * 2 * Math.PI
    cumulative += s.value
    const endAngle = (cumulative / total.value) * 2 * Math.PI

    const r = 50
    const cx = 60
    const cy = 60

    const x1 = cx + r * Math.cos(startAngle - Math.PI / 2)
    const y1 = cy + r * Math.sin(startAngle - Math.PI / 2)
    const x2 = cx + r * Math.cos(endAngle - Math.PI / 2)
    const y2 = cy + r * Math.sin(endAngle - Math.PI / 2)

    const largeArc = endAngle - startAngle > Math.PI ? 1 : 0

    const path = props.data.length === 1
      ? `M ${cx - r} ${cy} a ${r} ${r} 0 1 0 ${r * 2} 0 a ${r} ${r} 0 1 0 ${-r * 2} 0`
      : `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`

    return {
      ...s,
      path,
      color: s.color ?? PALETTE[idx % PALETTE.length],
      pct: ((s.value / total.value) * 100).toFixed(1),
    }
  })
})
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-donut-widget">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <div class="admin-donut-widget__row">
      <svg viewBox="0 0 120 120" class="admin-donut-widget__svg" role="img" :aria-label="title">
        <path
          v-for="(s, idx) in slices"
          :key="idx"
          :d="s.path"
          :fill="s.color"
        >
          <title>{{ s.label }}: {{ s.value }} ({{ s.pct }}%)</title>
        </path>
        <circle cx="60" cy="60" r="30" fill="var(--uid-surface-raised)" />
      </svg>
      <ul class="admin-donut-widget__legend">
        <li v-for="(s, idx) in slices" :key="idx">
          <span class="admin-donut-widget__dot" :style="{ background: s.color }" />
          <span class="admin-donut-widget__label">{{ s.label }}</span>
          <span class="admin-donut-widget__value">{{ s.pct }}%</span>
        </li>
      </ul>
    </div>
  </UidCard>
</template>

<style>
.admin-donut-widget__row {
  display: flex;
  align-items: center;
  gap: var(--uid-space-md);
}
.admin-donut-widget__svg {
  width: 120px;
  height: 120px;
  flex: none;
}
.admin-donut-widget__legend {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs);
}
.admin-donut-widget__legend li {
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  font-size: var(--uid-font-size-xs);
}
.admin-donut-widget__dot {
  width: 10px;
  height: 10px;
  border-radius: var(--uid-radius-full);
  flex: none;
}
.admin-donut-widget__label { flex: 1; color: var(--uid-text-primary); }
.admin-donut-widget__value {
  color: var(--uid-text-tertiary);
  font-variant-numeric: tabular-nums;
}
</style>

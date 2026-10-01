<script setup lang="ts">
/**
 * BarChartWidget — the `bar-chart` manifest widget: one series of
 * {label, value} points, drawn by CartesianChartWidget.
 *
 * Manifest:
 *   { type: 'bar-chart', title: '30 days',
 *     data: [{ label: '01', value: 12 }, ...],
 *     accent: 'var(--uid-accent)' }
 *
 * Several series go through the `chart` widget with chartType 'bar'.
 */
import { computed } from 'vue'
import CartesianChartWidget from './CartesianChartWidget.vue'

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
  /** The series name in the tooltip; the title by default. */
  seriesLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  accent: 'var(--uid-accent)',
  height: 200,
  seriesLabel: '',
})

const labels = computed(() => (props.data ?? []).map((d) => String(d.label)))
const series = computed(() => [
  {
    label: props.seriesLabel || props.title || '',
    data: (props.data ?? []).map((d) => (Number.isFinite(Number(d.value)) ? Number(d.value) : null)),
    color: props.accent,
  },
])
</script>

<template>
  <CartesianChartWidget
    kind="bar"
    :title="title"
    :description="description"
    :labels="labels"
    :series="series"
    :height="height"
  />
</template>

<style>
/*
 * The common header and empty state of the dashboard widgets. They live here
 * because the built-in bundle always loads this component.
 */
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
.admin-widget__empty {
  flex: 1 1 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--uid-text-tertiary, #9ca3af);
  font-size: var(--uid-font-size-sm);
}
</style>

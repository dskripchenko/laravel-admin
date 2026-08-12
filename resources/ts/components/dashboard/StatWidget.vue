<script setup lang="ts">
import { computed } from 'vue'
import { UidStat } from '@dskripchenko/ui'
import type { StatTone } from '@dskripchenko/ui'

type SemanticTone = 'neutral' | 'positive' | 'negative' | 'warning' | 'info'

/**
 * The backend's StatsOverviewWidget::data() returns an array of
 * `stats: [{label, value, change: {delta, direction}, color, icon}]`. This
 * StatWidget takes the first stat and renders a UidStat; several stats in one
 * widget are not supported yet, and a host that needs them declares several
 * StatWidgets.
 *
 * It also works with the older scalar values, where the widget sent `value`
 * directly, but stats[0] wins.
 */
interface StatChange {
  delta?: number
  direction?: 'up' | 'down' | 'flat'
}
interface StatItem {
  label?: string
  value?: number | string
  prefix?: string
  suffix?: string
  change?: StatChange | null
  color?: string | null
  icon?: string | null
}

interface Props {
  title?: string
  /** Backend payload from StatsOverviewWidget. */
  stats?: StatItem[]
  /** The older scalar value, for a host that passes it outside the array. */
  value?: number | string
  prefix?: string
  suffix?: string
  trend?: number
  precision?: number
  tone?: SemanticTone
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  stats: () => [],
  value: 0,
  prefix: '',
  suffix: '',
  trend: undefined,
  precision: 0,
  tone: 'neutral',
  loading: false,
})

const TONE_MAP: Record<SemanticTone, StatTone> = {
  neutral: 'primary',
  positive: 'success',
  negative: 'danger',
  warning: 'warning',
  info: 'info',
}

const uidTone = computed<StatTone>(() => TONE_MAP[props.tone])

/**
 * Resolves the first stat of the array, falling back to the scalar props.
 * UidStat takes a label on top, a value beneath and an optional trend.
 */
const first = computed<StatItem>(() => props.stats[0] ?? {})

const statLabel = computed<string>(
  () => first.value.label ?? props.title ?? '',
)
const statValue = computed<number | string>(
  () => first.value.value ?? props.value,
)
const statPrefix = computed<string>(() => first.value.prefix ?? props.prefix)
const statSuffix = computed<string>(() => first.value.suffix ?? props.suffix)
const statTrend = computed<number | undefined>(() => {
  const c = first.value.change
  if (c && typeof c.delta === 'number') return c.delta
  return props.trend
})
</script>

<template>
  <UidStat
    :title="title || statLabel"
    :value="statValue"
    :prefix="statPrefix"
    :suffix="statSuffix"
    :trend="statTrend"
    :precision="precision"
    :tone="uidTone"
    :loading="loading"
  />
</template>

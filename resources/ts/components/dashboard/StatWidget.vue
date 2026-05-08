<script setup lang="ts">
import { computed } from 'vue'
import { UidStat } from '@dskripchenko/ui'
import type { StatTone } from '@dskripchenko/ui'

type SemanticTone = 'neutral' | 'positive' | 'negative' | 'warning' | 'info'

/**
 * Backend StatsOverviewWidget::data() отдаёт массив `stats: [{label, value,
 * change: {delta, direction}, color, icon}]`. Frontend StatWidget берёт
 * первый stat и рендерит UidStat. Несколько stats в одном виджете пока
 * не поддерживаются (если нужно — host задаёт несколько StatWidget'ов).
 *
 * Для legacy scalar-values (если widget сам прислал `value` напрямую) тоже
 * работает — приоритет имеет stats[0].
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
  /** Legacy scalar value (если host передал не через массив). */
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
 * Резолвим первый stat из массива (или fallback на scalar props).
 * UidStat принимает label-сверху + value-снизу + опц. trend.
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

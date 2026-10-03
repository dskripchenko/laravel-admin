<script setup lang="ts">
/**
 * The stats widget — the backend's StatsOverviewWidget, whose data() returns
 * `stats: [{label, value, change: {delta, direction}, color, icon}]`.
 *
 * One stat renders as a single UidStat card; several render as a responsive
 * row of cards, each with its own label, value, trend, color and icon, under
 * the widget's title when it has one.
 *
 * It also works with the older scalar props, where the widget sent `value`
 * directly; a non-empty `stats` array wins.
 */
import { computed, type Component } from 'vue'
import { UidStat } from '@dskripchenko/ui'
import type { StatTone } from '@dskripchenko/ui'
import { resolveIcon } from '../shell/iconRegistry'
import { currentLocale, formatNumber, intlLocale } from '../../stores/i18n'
import { useLocaleStore } from '../../stores/locale'

type SemanticTone = 'neutral' | 'positive' | 'negative' | 'warning' | 'info'

interface StatChange {
  delta?: number
  direction?: 'up' | 'down' | 'flat'
}
interface StatFormat {
  style?: 'currency'
  currency?: string
  decimals?: number
}
interface StatItem {
  label?: string
  value?: number | string
  prefix?: string
  suffix?: string
  /** StatsOverviewWidget::money(). */
  format?: StatFormat | null
  /** StatsOverviewWidget::precision(). */
  precision?: number | null
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

/** Semantic and kit tone names, plus the usual color words, onto UidStat tones. */
const TONES: Record<string, StatTone> = {
  neutral: 'primary',
  primary: 'primary',
  default: 'primary',
  positive: 'success',
  success: 'success',
  green: 'success',
  negative: 'danger',
  danger: 'danger',
  error: 'danger',
  red: 'danger',
  warning: 'warning',
  yellow: 'warning',
  orange: 'warning',
  info: 'info',
  blue: 'info',
}

function toneOf(color: string | null | undefined): StatTone {
  return TONES[(color ?? '').toLowerCase()] ?? TONES[props.tone] ?? 'primary'
}

/** A trend's sign follows its direction: `down` with a positive delta is a fall. */
function trendOf(change: StatChange | null | undefined): number | undefined {
  if (!change || typeof change.delta !== 'number') return undefined
  const abs = Math.abs(change.delta)
  if (change.direction === 'down') return -abs
  if (change.direction === 'up') return abs
  return change.delta
}

interface ResolvedStat {
  title: string
  value: number | string
  prefix: string
  suffix: string
  trend: number | undefined
  tone: StatTone
  icon: Component | undefined
  precision: number
  formatter: ((value: number | string) => string) | undefined
}

/**
 * Money is formatted here, by the panel's locale — UidStat only knows plain
 * numbers. A value that is not a number is shown as it came.
 */
function moneyFormatter(format: StatFormat | null | undefined): ((value: number | string) => string) | undefined {
  if (format?.style !== 'currency' || !format.currency) return undefined
  const digits = format.decimals ?? 0
  return (value) => {
    const n = typeof value === 'number' ? value : Number(value)
    if (typeof value === 'string' && (value.trim() === '' || Number.isNaN(n))) return value
    try {
      return formatNumber(n, {
        style: 'currency',
        currency: format.currency!,
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
      })
    } catch {
      return `${formatNumber(n, { minimumFractionDigits: digits, maximumFractionDigits: digits })} ${format.currency}`
    }
  }
}

const items = computed<ResolvedStat[]>(() => {
  if (props.stats.length === 0) {
    return [{
      title: props.title,
      value: props.value,
      prefix: props.prefix,
      suffix: props.suffix,
      trend: props.trend,
      tone: toneOf(null),
      icon: undefined,
      precision: props.precision,
      formatter: undefined,
    }]
  }
  // A lone stat whose label is the widget's title (or that has none) is one
  // card titled with it; one with a label of its own sits under the widget's
  // title, like a set of cards does, so a row of stat widgets lines up.
  const bare = props.stats.length === 1 && !hasOwnLabel.value
  return props.stats.map((s) => ({
    title: (bare && props.title ? props.title : s.label) ?? '',
    value: s.value ?? '',
    prefix: s.prefix ?? '',
    suffix: s.suffix ?? '',
    trend: trendOf(s.change),
    tone: toneOf(s.color),
    icon: resolveIcon(s.icon) ?? undefined,
    precision: typeof s.precision === 'number' ? s.precision : props.precision,
    formatter: moneyFormatter(s.format),
  }))
})

const multiple = computed(() => items.value.length > 1)
/** A single stat labelled differently from the widget's title. */
const hasOwnLabel = computed(() => {
  if (props.stats.length !== 1 || !props.title) return false
  const label = props.stats[0]!.label
  return typeof label === 'string' && label !== '' && label !== props.title
})
/** Drawn as a titled section of cards rather than one bare card. */
const sectioned = computed(() => multiple.value || hasOwnLabel.value)
/** The panel's locale for the plain numbers and the trends, as for the money. */
// Reactive to a locale switch where the store is there; the document's
// language otherwise (a widget mounted on its own, a test).
let localeStore: ReturnType<typeof useLocaleStore> | null = null
try {
  localeStore = useLocaleStore()
} catch {
  // no active Pinia
}
const locale = computed(() => intlLocale(localeStore?.current ?? currentLocale()))
</script>

<template>
  <UidStat
    v-if="!sectioned"
    :title="items[0]!.title"
    :value="items[0]!.value"
    :prefix="items[0]!.prefix"
    :suffix="items[0]!.suffix"
    :trend="items[0]!.trend"
    :precision="items[0]!.precision"
    :formatter="items[0]!.formatter"
    :locale="locale"
    :tone="items[0]!.tone"
    :icon="items[0]!.icon"
    :loading="loading"
    trend-placement="below"
    class="admin-stats-widget__single"
  />
  <section v-else class="admin-stats-widget">
    <header v-if="title" class="admin-stats-widget__hd">
      <h3 class="admin-stats-widget__title">{{ title }}</h3>
    </header>
    <div class="admin-stats-widget__grid">
      <UidStat
        v-for="(stat, idx) in items"
        :key="idx"
        :title="stat.title"
        :value="stat.value"
        :prefix="stat.prefix"
        :suffix="stat.suffix"
        :trend="stat.trend"
        :precision="stat.precision"
        :formatter="stat.formatter"
        :locale="locale"
        :tone="stat.tone"
        :icon="stat.icon"
        :loading="loading"
        trend-placement="below"
      />
    </div>
  </section>
</template>

<style>
.admin-stats-widget {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
  min-width: 0;
  /* Fill the dashboard cell, so the cards of stat widgets in one row share
     their height whatever each card holds. */
  height: 100%;
  box-sizing: border-box;
}
.admin-stats-widget__title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-stats-widget__single {
  /* A lone card fills its dashboard cell, level with the cards next to it. */
  height: 100%;
  box-sizing: border-box;
}
.admin-stats-widget__grid {
  flex: 1;
  grid-auto-rows: 1fr;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--uid-space-md);
}
</style>

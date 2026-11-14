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
import { UidBadge, UidCard, UidStat } from '@dskripchenko/ui'
import type { StatTone } from '@dskripchenko/ui'
import { resolveIcon } from '../shell/iconRegistry'
import { badgeVariant, resolveTone, type BadgeVariant, type PanelTone } from '../tones'
import WidgetHeader from './WidgetHeader.vue'
import ListRows, { type ListRowItem } from './ListRows.vue'
import { currentLocale, formatNumber, intlLocale } from '../../stores/i18n'
import { useLocaleStore } from '../../stores/locale'
import { trSafe as tr } from '../../stores/i18n'

interface StatChange {
  delta?: number
  direction?: 'up' | 'down' | 'flat'
  /** 'pp' — percentage points (StatsOverviewWidget::share(), 1.75). */
  unit?: string | null
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
  /** Where the card leads (StatsOverviewWidget::url()): a panel path or an external address. */
  url?: string | null
  /** The value of the previous period, when the dashboard compares. */
  previous?: number | string | null
  /** A line under the number (StatsOverviewWidget::stat(..., hint: …)). */
  hint?: string | null
  /** The hint as a badge of this tone (StatsOverviewWidget::hintTone(), 1.75). */
  hintTone?: string | null
  /** A share in per cent: the change is in percentage points (share(), 1.75). */
  share?: boolean
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
  /** A name from the panel's tone vocabulary (../tones.ts); primary when unset. */
  tone?: string
  loading?: boolean
  /** null — cards; compact — numbers in a row in one card; tiles (1.75). */
  layout?: 'compact' | 'tiles' | null
  /** Rows under the numbers (StatsOverviewWidget::item(), 1.75). */
  items?: ListRowItem[] | null
  emptyText?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  layout: null,
  items: null,
  emptyText: null,
  title: '',
  stats: () => [],
  value: 0,
  prefix: '',
  suffix: '',
  trend: undefined,
  precision: 0,
  tone: undefined,
  loading: false,
})

/**
 * A stat's colour, from the panel's one tone vocabulary (../tones.ts): the
 * stat's own `color`, else the widget's `tone`, else primary. UidStat has no
 * neutral tone, so a gray stat is drawn as primary and greyed by a class.
 */
function panelToneOf(color: string | null | undefined): PanelTone {
  return resolveTone(color) ?? resolveTone(props.tone) ?? 'primary'
}

function toneOf(color: string | null | undefined): StatTone {
  const tone = panelToneOf(color)
  return tone === 'neutral' ? 'primary' : tone
}

function toneClass(color: string | null | undefined): string | undefined {
  return panelToneOf(color) === 'neutral' ? 'admin-stat--neutral' : undefined
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
  toneClass: string | undefined
  icon: Component | undefined
  precision: number
  formatter: ((value: number | string) => string) | undefined
  url: string | null
  previous: string | null
  /** A line under the number: «старейшее 26 ч» (stat(..., hint: …)). */
  hint: string | null
  /** The hint as a badge of this variant (hintTone()); null — plain text. */
  hintBadge: BadgeVariant | null
  /** The suffix after the trend number: per cent, or percentage points for a share. */
  trendSuffix: string
  /** The panel tone of the stat, for the compact and tile views. */
  panelTone: PanelTone
}

/** A link's kind: inside the panel (the router) or outside it (a new tab). */
function isExternal(url: string): boolean {
  return /^[a-z]+:\/\//i.test(url)
}

/**
 * The trend from the previous period's value when the card has none of its
 * own: the change in per cent.
 */
function trendFrom(value: unknown, previous: unknown, share = false): number | undefined {
  const a = typeof value === 'number' ? value : Number(value)
  const b = typeof previous === 'number' ? previous : Number(previous)
  if (!Number.isFinite(a) || !Number.isFinite(b)) return undefined
  // A share changes by percentage points: 0.8 % → 0.6 % is −0.2 p.p.
  if (share) return Math.round((a - b) * 100) / 100
  if (b === 0) return undefined
  return Math.round(((a - b) / Math.abs(b)) * 1000) / 10
}

/** The suffix of a trend's number: per cent, or percentage points. */
function trendSuffixOf(s: StatItem): string {
  const pp = s.change?.unit === 'pp' || (s.share === true && !(s.change && s.change.unit))
  return pp ? ` ${tr('п.п.')}` : '%'
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
      toneClass: toneClass(null),
      icon: undefined,
      precision: props.precision,
      formatter: undefined,
      url: null,
      previous: null,
      hint: null,
      hintBadge: null,
      trendSuffix: '%',
      panelTone: panelToneOf(null),
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
    trend: trendOf(s.change) ?? (s.previous !== undefined && s.previous !== null ? trendFrom(s.value, s.previous, s.share === true) : undefined),
    trendSuffix: trendSuffixOf(s),
    panelTone: panelToneOf(s.color),
    hintBadge: typeof s.hintTone === 'string' && s.hintTone !== '' ? badgeVariant(s.hintTone) : null,
    tone: toneOf(s.color),
    toneClass: toneClass(s.color),
    icon: resolveIcon(s.icon) ?? undefined,
    precision: typeof s.precision === 'number' ? s.precision : props.precision,
    formatter: moneyFormatter(s.format),
    url: typeof s.url === 'string' && s.url !== '' ? s.url : null,
    hint: typeof s.hint === 'string' && s.hint !== '' ? s.hint : null,
    previous: s.previous === undefined || s.previous === null
      ? null
      : (moneyFormatter(s.format)?.(s.previous) ?? (typeof s.previous === 'number' ? formatNumber(s.previous) : String(s.previous))),
  }))
})

const multiple = computed(() => items.value.length > 1)

/**
 * The compact and tile views (1.75): compact() — or rows under the numbers —
 * puts the numbers in a row inside one card; tiles() draws small tiles.
 */
const view = computed<'compact' | 'tiles' | null>(() => {
  if (props.layout === 'compact' || props.layout === 'tiles') return props.layout
  return Array.isArray(props.items) ? 'compact' : null
})
const listItems = computed<ListRowItem[] | null>(() => (Array.isArray(props.items) ? props.items : null))

/** The value as the compact and tile views show it, formatted in the panel's locale. */
function displayValue(stat: ResolvedStat): string {
  let text: string
  if (stat.formatter) {
    text = stat.formatter(stat.value)
  } else if (typeof stat.value === 'number') {
    text = formatNumber(stat.value, { minimumFractionDigits: stat.precision, maximumFractionDigits: stat.precision })
  } else {
    text = String(stat.value)
  }
  return `${stat.prefix}${text}${stat.suffix}`
}

/** The trend of the compact and tile views: an arrow, the number and its suffix. */
function trendText(stat: ResolvedStat): string {
  if (stat.trend === undefined) return ''
  const arrow = stat.trend > 0 ? '▲' : stat.trend < 0 ? '▼' : '–'
  return `${arrow} ${formatNumber(Math.abs(stat.trend), { maximumFractionDigits: 2 })}${stat.trendSuffix}`
}

function trendClass(stat: ResolvedStat): string {
  if (stat.trend === undefined || stat.trend === 0) return 'admin-stats-trend--flat'
  return stat.trend > 0 ? 'admin-stats-trend--up' : 'admin-stats-trend--down'
}

/** The number is tinted only by an alerting tone: success, warning, danger. */
function valueClass(stat: ResolvedStat): string | undefined {
  return ['success', 'warning', 'danger'].includes(stat.panelTone) ? `admin-stats-value--${stat.panelTone}` : undefined
}

function linkAttrs(url: string | null): Record<string, string> {
  if (!url) return {}
  return isExternal(url) ? { href: url, target: '_blank', rel: 'noopener' } : { to: url }
}
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
  <!-- compact(): the numbers in a row inside one card, rows under them (1.75). -->
  <UidCard v-if="view === 'compact'" padding="md" class="admin-widget admin-stats-compact" data-testid="widget-stats-compact">
    <WidgetHeader :title="title" />
    <div class="admin-widget__body admin-stats-compact__body">
      <div v-if="stats.length > 0" class="admin-stats-compact__row" :style="{ '--admin-stats-count': items.length }">
        <component
          :is="stat.url ? (isExternal(stat.url) ? 'a' : 'router-link') : 'div'"
          v-for="(stat, idx) in items"
          :key="idx"
          class="admin-stats-compact__item"
          :class="{ 'admin-stats-compact__item--link': !!stat.url }"
          :data-testid="stat.url ? 'stat-link' : 'stat-item'"
          v-bind="linkAttrs(stat.url)"
        >
          <span class="admin-stats-compact__label" :title="stat.title">{{ stat.title }}</span>
          <b class="admin-stats-compact__value" :class="valueClass(stat)">{{ displayValue(stat) }}</b>
          <span v-if="stat.trend !== undefined" class="admin-stats-trend" :class="trendClass(stat)" data-testid="stat-trend">{{ trendText(stat) }}</span>
          <UidBadge v-if="stat.hint !== null && stat.hintBadge" :variant="stat.hintBadge" size="sm" class="admin-stats-widget__hint-badge" data-testid="stat-hint">{{ stat.hint }}</UidBadge>
          <span v-else-if="stat.hint !== null" class="admin-stats-widget__hint" data-testid="stat-hint">{{ stat.hint }}</span>
          <span v-if="stat.previous !== null" class="admin-stats-widget__previous" data-testid="stat-previous">
            {{ tr('Прошлый период') }}: {{ stat.previous }}
          </span>
        </component>
      </div>
      <ListRows v-if="listItems !== null" :items="listItems" :empty-text="emptyText" class="admin-stats-compact__list" />
    </div>
  </UidCard>
  <!-- tiles(): small bordered tiles — a caption, the number, the hint (1.75). -->
  <section v-else-if="view === 'tiles'" class="admin-stats-widget admin-stats-tiles" data-testid="widget-stats-tiles">
    <WidgetHeader :title="title" title-class="admin-stats-widget__title" />
    <div class="admin-stats-tiles__grid">
      <component
        :is="stat.url ? (isExternal(stat.url) ? 'a' : 'router-link') : 'div'"
        v-for="(stat, idx) in items"
        :key="idx"
        class="admin-stats-tile"
        :class="{ 'admin-stats-tile--link': !!stat.url }"
        :data-testid="stat.url ? 'stat-link' : 'stat-tile'"
        v-bind="linkAttrs(stat.url)"
      >
        <span class="admin-stats-tile__label" :title="stat.title">{{ stat.title }}</span>
        <span class="admin-stats-tile__row">
          <b class="admin-stats-tile__value" :class="valueClass(stat)">{{ displayValue(stat) }}</b>
          <UidBadge v-if="stat.hint !== null && stat.hintBadge" :variant="stat.hintBadge" size="sm" class="admin-stats-widget__hint-badge" data-testid="stat-hint">{{ stat.hint }}</UidBadge>
          <span v-else-if="stat.hint !== null" class="admin-stats-widget__hint" data-testid="stat-hint">{{ stat.hint }}</span>
        </span>
        <span v-if="stat.trend !== undefined" class="admin-stats-trend" :class="trendClass(stat)" data-testid="stat-trend">{{ trendText(stat) }}</span>
      </component>
    </div>
    <ListRows v-if="listItems !== null" :items="listItems" :empty-text="emptyText" />
  </section>
  <component
    :is="items[0]!.url ? (isExternal(items[0]!.url) ? 'a' : 'router-link') : 'div'"
    v-else-if="!sectioned && items[0]!.url"
    class="admin-stats-widget__link admin-stats-widget__link--single"
    data-testid="stat-link"
    v-bind="isExternal(items[0]!.url) ? { href: items[0]!.url, target: '_blank', rel: 'noopener' } : { to: items[0]!.url }"
  >
    <UidStat
      :title="items[0]!.title"
      :value="items[0]!.value"
      :prefix="items[0]!.prefix"
      :suffix="items[0]!.suffix"
      :trend="items[0]!.trend"
      :trend-suffix="items[0]!.trendSuffix"
      :precision="items[0]!.precision"
      :formatter="items[0]!.formatter"
      :locale="locale"
      :tone="items[0]!.tone"
      :icon="items[0]!.icon"
      :loading="loading"
      trend-placement="below"
      :class="['admin-stats-widget__single', items[0]!.toneClass]"
    />
    <UidBadge v-if="items[0]!.hint !== null && items[0]!.hintBadge" :variant="items[0]!.hintBadge" size="sm" class="admin-stats-widget__hint-badge" data-testid="stat-hint">{{ items[0]!.hint }}</UidBadge>
    <span v-else-if="items[0]!.hint !== null" class="admin-stats-widget__hint" data-testid="stat-hint">{{ items[0]!.hint }}</span>
    <span v-if="items[0]!.previous !== null" class="admin-stats-widget__previous" data-testid="stat-previous">
      {{ tr('Прошлый период') }}: {{ items[0]!.previous }}
    </span>
  </component>
  <div v-else-if="!sectioned && (items[0]!.hint !== null || items[0]!.previous !== null)" class="admin-stats-widget__link--single">
    <UidStat
      :title="items[0]!.title"
      :value="items[0]!.value"
      :prefix="items[0]!.prefix"
      :suffix="items[0]!.suffix"
      :trend="items[0]!.trend"
      :trend-suffix="items[0]!.trendSuffix"
      :precision="items[0]!.precision"
      :formatter="items[0]!.formatter"
      :locale="locale"
      :tone="items[0]!.tone"
      :icon="items[0]!.icon"
      :loading="loading"
      trend-placement="below"
      :class="['admin-stats-widget__single', items[0]!.toneClass]"
  />
    <UidBadge v-if="items[0]!.hint !== null && items[0]!.hintBadge" :variant="items[0]!.hintBadge" size="sm" class="admin-stats-widget__hint-badge" data-testid="stat-hint">{{ items[0]!.hint }}</UidBadge>
    <span v-else-if="items[0]!.hint !== null" class="admin-stats-widget__hint" data-testid="stat-hint">{{ items[0]!.hint }}</span>
    <span v-if="items[0]!.previous !== null" class="admin-stats-widget__previous" data-testid="stat-previous">
      {{ tr('Прошлый период') }}: {{ items[0]!.previous }}
    </span>
  </div>
  <UidStat
    v-else-if="!sectioned"
    :title="items[0]!.title"
    :value="items[0]!.value"
    :prefix="items[0]!.prefix"
    :suffix="items[0]!.suffix"
    :trend="items[0]!.trend"
    :trend-suffix="items[0]!.trendSuffix"
    :precision="items[0]!.precision"
    :formatter="items[0]!.formatter"
    :locale="locale"
    :tone="items[0]!.tone"
    :icon="items[0]!.icon"
    :loading="loading"
    trend-placement="below"
    :class="['admin-stats-widget__single', items[0]!.toneClass]"
  />
  <section v-else class="admin-stats-widget">
    <WidgetHeader :title="title" title-class="admin-stats-widget__title" />
    <div class="admin-stats-widget__grid">
      <component
        :is="stat.url ? (isExternal(stat.url) ? 'a' : 'router-link') : 'div'"
        v-for="(stat, idx) in items"
        :key="idx"
        class="admin-stats-widget__link"
        :data-testid="stat.url ? 'stat-link' : undefined"
        v-bind="stat.url ? (isExternal(stat.url) ? { href: stat.url, target: '_blank', rel: 'noopener' } : { to: stat.url }) : {}"
      >
      <UidStat
        :title="stat.title"
        :value="stat.value"
        :prefix="stat.prefix"
        :suffix="stat.suffix"
        :trend="stat.trend"
        :trend-suffix="stat.trendSuffix"
        :precision="stat.precision"
        :formatter="stat.formatter"
        :locale="locale"
        :tone="stat.tone"
        :icon="stat.icon"
        :loading="loading"
        :class="stat.toneClass"
        trend-placement="below"
      />
      <UidBadge v-if="stat.hint !== null && stat.hintBadge" :variant="stat.hintBadge" size="sm" class="admin-stats-widget__hint-badge" data-testid="stat-hint">{{ stat.hint }}</UidBadge>
      <span v-else-if="stat.hint !== null" class="admin-stats-widget__hint" data-testid="stat-hint">{{ stat.hint }}</span>
      <span v-if="stat.previous !== null" class="admin-stats-widget__previous" data-testid="stat-previous">
        {{ tr('Прошлый период') }}: {{ stat.previous }}
      </span>
      </component>
    </div>
  </section>
</template>

<style>
/* compact() (1.75): the numbers in a row inside one card, no minimum width. */
.admin-stats-compact__body {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 12px);
  min-width: 0;
}
.admin-stats-compact__row {
  display: grid;
  grid-template-columns: repeat(var(--admin-stats-count, 3), minmax(0, 1fr));
  gap: var(--uid-space-sm, 8px);
}
.admin-stats-compact__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}
.admin-stats-compact__item--link:hover .admin-stats-compact__value {
  text-decoration: underline;
}
.admin-stats-compact__label,
.admin-stats-tile__label {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-stats-compact__value {
  font-size: 20px;
  font-weight: var(--uid-font-weight-semibold, 600);
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  color: var(--uid-text-primary);
  overflow-wrap: anywhere;
}
.admin-stats-value--success { color: var(--uid-color-success-text, var(--uid-color-success)); }
.admin-stats-value--warning { color: var(--uid-color-warning-text, var(--uid-color-warning)); }
.admin-stats-value--danger { color: var(--uid-color-danger-text, var(--uid-color-danger)); }
.admin-stats-trend {
  font-size: var(--uid-font-size-xs, 12px);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.admin-stats-trend--up { color: var(--uid-color-success-text, var(--uid-color-success)); }
.admin-stats-trend--down { color: var(--uid-color-danger-text, var(--uid-color-danger)); }
.admin-stats-trend--flat { color: var(--uid-text-tertiary); }
.admin-stats-compact__list {
  border-top: 1px solid var(--uid-border-subtle, var(--uid-color-zinc-100, #f4f4f5));
}
/* tiles() (1.75): small bordered tiles, a caption over the number and its hint. */
.admin-stats-tiles__grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--uid-space-sm, 12px);
}
.admin-stats-tile {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: 12px 14px;
  border: 1px solid var(--uid-border-default, var(--uid-color-zinc-200, #e4e4e7));
  border-radius: var(--uid-radius-lg, 10px);
  background: var(--uid-surface-raised, #fff);
  color: var(--uid-text-primary);
  text-decoration: none;
}
.admin-stats-tile--link:hover {
  border-color: var(--uid-color-zinc-400, #a1a1aa);
}
.admin-stats-tile__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-xs, 8px);
  min-width: 0;
}
.admin-stats-tile__value {
  font-size: 24px;
  font-weight: var(--uid-font-weight-semibold, 600);
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}
.admin-stats-widget__hint-badge {
  align-self: flex-start;
  white-space: nowrap;
}
.admin-stats-widget > .admin-widget__hd {
  margin-bottom: 0;
}
/* A card that leads to its list (StatsOverviewWidget::url()). */
.admin-stats-widget__link {
  display: flex;
  flex-direction: column;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}
.admin-stats-widget__link--single {
  height: 100%;
}
.admin-stats-widget__link > .uid-stat {
  flex: 1 1 auto;
}
a.admin-stats-widget__link > .uid-stat,
a.admin-stats-widget__link--single > .uid-stat {
  transition: box-shadow var(--uid-duration-fast, 120ms) var(--uid-ease-out, ease);
}
a.admin-stats-widget__link:hover > .uid-stat {
  box-shadow: 0 0 0 1px var(--uid-color-primary, #14b8a6);
}
.admin-stats-widget__hint {
  display: block;
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
.admin-stats-widget__previous {
  margin-top: 4px;
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-tertiary);
}
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
/* UidStat has no neutral tone: a gray stat is primary with a grey icon. */
.admin-stat--neutral .uid-stat__icon {
  background: color-mix(in srgb, var(--uid-color-zinc-400) 16%, transparent);
  color: var(--uid-color-zinc-500, var(--uid-color-zinc-400));
}
.admin-stats-widget__grid {
  flex: 1;
  grid-auto-rows: 1fr;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--uid-space-md);
}
</style>

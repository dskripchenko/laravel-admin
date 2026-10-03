<script setup lang="ts">
/**
 * RadarChartWidget — one polygon per series over a radial grid, in plain SVG.
 *
 * Every label is a spoke; the rings follow nice ticks from zero to the
 * largest value. Hovering anywhere picks the nearest spoke and shows each
 * series' value on it.
 */
import { computed, ref } from 'vue'
import { UidCard } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'
import ChartLegend from './ChartLegend.vue'
import ChartTooltip from './ChartTooltip.vue'
import { useChartSize } from './useChartSize'
import './chart.css'
import { formatTick, formatValue, isEmptySeries, niceScale, type ChartSeries, type ChartValueFormat } from './chartGeometry'

interface Props {
  title?: string
  description?: string
  labels: string[]
  series: ChartSeries[]
  height?: number
  /** ChartWidget::money() / precision(): how the values read. */
  format?: ChartValueFormat | null
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  height: 180,
  format: null,
})

const box = ref<HTMLElement | null>(null)
const svgEl = ref<SVGSVGElement | null>(null)
const { width, height: boxHeight } = useChartSize(box, { width: 360, height: props.height })

const n = computed(() => props.labels.length)
// A radar needs at least three spokes to enclose anything.
const isEmpty = computed(() => n.value < 3 || isEmptySeries(props.series))

/** Room for the spoke labels around the grid. */
const LABEL_GAP = 16
const cx = computed(() => width.value / 2)
const cy = computed(() => boxHeight.value / 2)
const radius = computed(() => Math.max(20, Math.min(width.value / 2 - 56, boxHeight.value / 2 - LABEL_GAP - 8)))

const scale = computed(() => {
  let hi = 0
  for (const s of props.series) for (const v of s.data) if (v !== null && v > hi) hi = v
  return niceScale(0, hi, 4)
})

function angle(i: number): number {
  return -Math.PI / 2 + (2 * Math.PI * i) / Math.max(1, n.value)
}

function point(i: number, v: number): { x: number; y: number } {
  const r = (Math.max(0, v) / (scale.value.max || 1)) * radius.value
  return { x: cx.value + r * Math.cos(angle(i)), y: cy.value + r * Math.sin(angle(i)) }
}

const fmt = (v: number): string => (Math.round(v * 100) / 100).toString()

const rings = computed(() =>
  scale.value.ticks
    .filter((t) => t > 0)
    .map((t) => ({
      value: t,
      label: formatTick(t, props.format),
      d:
        props.labels.map((_, i) => {
          const p = point(i, t)
          return `${i === 0 ? 'M' : 'L'}${fmt(p.x)} ${fmt(p.y)}`
        }).join(' ') + ' Z',
    })),
)

const spokes = computed(() =>
  props.labels.map((label, i) => {
    const end = point(i, scale.value.max)
    const a = angle(i)
    const lx = cx.value + (radius.value + LABEL_GAP) * Math.cos(a)
    const ly = cy.value + (radius.value + LABEL_GAP) * Math.sin(a)
    const cos = Math.cos(a)
    return {
      x2: end.x,
      y2: end.y,
      lx,
      ly,
      anchor: Math.abs(cos) < 0.2 ? 'middle' : cos > 0 ? 'start' : 'end',
      text: label.length > 14 ? `${label.slice(0, 13)}…` : label,
    }
  }),
)

const polygons = computed(() =>
  props.series.map((s) => {
    const pts = props.labels.map((_, i) => ({ i, ...point(i, s.data[i] ?? 0) }))
    return {
      color: s.color,
      d: pts.map((p, k) => `${k === 0 ? 'M' : 'L'}${fmt(p.x)} ${fmt(p.y)}`).join(' ') + ' Z',
      points: pts,
    }
  }),
)

const hover = ref<number | null>(null)

function onMove(e: PointerEvent | MouseEvent): void {
  const rect = svgEl.value?.getBoundingClientRect()
  if (!rect || n.value === 0) return
  const dx = e.clientX - rect.left - cx.value
  const dy = e.clientY - rect.top - cy.value
  // The angle from the top, clockwise, onto the nearest spoke.
  let a = Math.atan2(dy, dx) + Math.PI / 2
  if (a < 0) a += 2 * Math.PI
  hover.value = Math.round(a / ((2 * Math.PI) / n.value)) % n.value
}

function onLeave(): void {
  hover.value = null
}

const tooltip = computed(() => {
  const i = hover.value
  if (i === null) return null
  const p = point(i, scale.value.max)
  return {
    x: p.x,
    y: Math.max(0, p.y - 8),
    title: props.labels[i] ?? '',
    rows: props.series.map((s) => ({ label: s.label, color: s.color, value: formatValue(s.data[i] ?? null, props.format) })),
  }
})

const legend = computed(() => props.series.map((s) => ({ label: s.label, color: s.color })))
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-chart admin-chart--radar">
    <header v-if="title || description" class="admin-widget__hd">
      <h3 v-if="title" class="admin-widget__title">{{ title }}</h3>
      <p v-if="description" class="admin-widget__desc">{{ description }}</p>
    </header>
    <div v-if="isEmpty" class="admin-widget__empty">{{ tr('Нет данных') }}</div>
    <template v-else>
      <div ref="box" class="admin-chart__box" :style="{ minHeight: `${height}px` }">
        <svg
          ref="svgEl"
          class="admin-chart__svg"
          :width="width"
          :height="boxHeight"
          :viewBox="`0 0 ${width} ${boxHeight}`"
          role="img"
          :aria-label="title || 'radar'"
          @pointermove="onMove"
          @mousemove="onMove"
          @pointerleave="onLeave"
          @mouseleave="onLeave"
        >
          <g class="admin-chart__grid">
            <path v-for="(r, idx) in rings" :key="`r${idx}`" class="admin-chart__grid-line" :d="r.d" fill="none" />
            <line
              v-for="(s, i) in spokes"
              :key="`s${i}`"
              :class="hover === i ? 'admin-chart__axis-line' : 'admin-chart__grid-line'"
              :x1="cx"
              :y1="cy"
              :x2="s.x2"
              :y2="s.y2"
            />
            <text
              v-for="(r, idx) in rings"
              :key="`rl${idx}`"
              class="admin-chart__tick"
              :x="cx + 4"
              :y="cy - (r.value / (scale.max || 1)) * radius"
              dominant-baseline="middle"
            >{{ r.label }}</text>
          </g>
          <g class="admin-chart__series">
            <g v-for="(p, si) in polygons" :key="si" :data-series="si">
              <path
                class="admin-chart__area admin-chart__radar-shape"
                :d="p.d"
                :fill="p.color"
                fill-opacity="0.14"
                :stroke="p.color"
              />
              <circle
                v-for="pt in p.points"
                :key="pt.i"
                class="admin-chart__point"
                :cx="pt.x"
                :cy="pt.y"
                :r="hover === pt.i ? 4.5 : 3"
                :fill="p.color"
              />
            </g>
          </g>
          <text
            v-for="(s, i) in spokes"
            :key="`l${i}`"
            class="admin-chart__tick admin-chart__radar-label"
            :x="s.lx"
            :y="s.ly"
            :text-anchor="s.anchor"
            dominant-baseline="middle"
          >{{ s.text }}</text>
        </svg>
        <ChartTooltip
          v-if="tooltip"
          :x="tooltip.x"
          :y="tooltip.y"
          :title="tooltip.title"
          :rows="tooltip.rows"
          :bounds-width="width"
        />
      </div>
      <ChartLegend v-if="series.length > 1" :items="legend" />
      <table class="admin-chart__sr">
        <thead>
          <tr>
            <th />
            <th v-for="(s, si) in series" :key="si" scope="col">{{ s.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(label, i) in labels" :key="i">
            <th scope="row">{{ label }}</th>
            <td v-for="(s, si) in series" :key="si">{{ formatValue(s.data[i] ?? null, format) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </UidCard>
</template>

<style>
.admin-chart__radar-shape {
  stroke-width: 2;
  stroke-linejoin: round;
}
.admin-chart__radar-label {
  fill: var(--uid-text-secondary);
}
</style>

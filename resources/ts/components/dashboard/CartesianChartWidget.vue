<script setup lang="ts">
/**
 * CartesianChartWidget — line, area and bar charts over a category axis, in
 * plain SVG with no charting library.
 *
 *   - several series: lines and areas are overlaid, bars are grouped;
 *     `stacked` stacks areas and bars (and lines, as running totals)
 *   - a y axis with nice ticks and recessive gridlines, an x axis whose labels
 *     thin out instead of colliding
 *   - hover: a crosshair (line, area) or a band highlight (bar) and a tooltip
 *     with every series' value at that category
 *   - a legend for two series and more, and a visually hidden data table
 *
 * It is laid out in real pixels from the container's size, so it follows the
 * dashboard cell when that is resized.
 */
import { computed, ref } from 'vue'
import { UidCard } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'
import ChartLegend from './ChartLegend.vue'
import ChartTooltip from './ChartTooltip.vue'
import { useChartSize } from './useChartSize'
import './chart.css'
import {
  estimateTextWidth,
  formatTick,
  formatValue,
  isEmptySeries,
  niceScale,
  stackSeries,
  type ChartSeries,
} from './chartGeometry'

export type CartesianKind = 'line' | 'area' | 'bar'

interface Props {
  kind?: CartesianKind
  title?: string
  description?: string
  labels: string[]
  series: ChartSeries[]
  stacked?: boolean
  /** The minimum plot height in px; the chart grows with its cell beyond it. */
  height?: number
}

const props = withDefaults(defineProps<Props>(), {
  kind: 'line',
  title: '',
  description: '',
  stacked: false,
  height: 200,
})

const box = ref<HTMLElement | null>(null)
const svgEl = ref<SVGSVGElement | null>(null)
const { width, height: boxHeight } = useChartSize(box, { width: 480, height: props.height })

const PAD_TOP = 8
const PAD_RIGHT = 12
const PAD_BOTTOM = 22
const AXIS_FONT = 11

const n = computed(() => props.labels.length)
const isEmpty = computed(() => n.value === 0 || isEmptySeries(props.series))
const isBar = computed(() => props.kind === 'bar')

/** The y value each series is drawn at: its own, or the running total when stacked. */
const tops = computed<Array<Array<number | null>>>(() => {
  if (!props.stacked) return props.series.map((s) => s.data.slice(0, n.value))
  const stacked = stackSeries(props.series, n.value)
  return stacked.map((row, si) => row.map((v, i) => (props.series[si].data[i] === null ? null : v)))
})

const scale = computed(() => {
  const values: number[] = []
  for (const row of tops.value) for (const v of row) if (v !== null) values.push(v)
  if (props.stacked && isBar.value) {
    // Negative segments stack downwards on their own.
    for (let i = 0; i < n.value; i++) {
      let neg = 0
      for (const s of props.series) {
        const v = s.data[i]
        if (v !== null && v !== undefined && v < 0) neg += v
      }
      values.push(neg)
    }
  }
  const lo = values.length ? Math.min(...values) : 0
  const hi = values.length ? Math.max(...values) : 1
  return niceScale(lo, hi, boxHeight.value < 160 ? 3 : 4)
})

const tickLabels = computed(() => scale.value.ticks.map((t) => formatTick(t)))
const padLeft = computed(
  () => Math.ceil(Math.max(...tickLabels.value.map((l) => estimateTextWidth(l, AXIS_FONT)), 8)) + 10,
)
const plotW = computed(() => Math.max(20, width.value - padLeft.value - PAD_RIGHT))
const plotH = computed(() => Math.max(20, boxHeight.value - PAD_TOP - PAD_BOTTOM))
const band = computed(() => plotW.value / Math.max(1, n.value))

function y(v: number): number {
  const { min, max } = scale.value
  return PAD_TOP + plotH.value * (1 - (v - min) / (max - min || 1))
}
const baseline = computed(() => y(Math.max(scale.value.min, Math.min(0, scale.value.max))))

function x(i: number): number {
  if (isBar.value) return padLeft.value + band.value * (i + 0.5)
  if (n.value <= 1) return padLeft.value + plotW.value / 2
  return padLeft.value + (plotW.value * i) / (n.value - 1)
}

const gridLines = computed(() =>
  scale.value.ticks.map((t, i) => ({ y: y(t), label: tickLabels.value[i], zero: t === 0 })),
)

/**
 * The x labels thin out to every k-th one when they would otherwise collide,
 * and are nudged inwards at the edges so none is clipped by the box.
 */
const xLabels = computed(() => {
  const short = props.labels.map((l) => (l.length > 16 ? `${l.slice(0, 15)}…` : l))
  const widths = short.map((l) => estimateTextWidth(l, AXIS_FONT))
  const widest = Math.max(...widths, 1) + 12
  const slot = isBar.value ? band.value : plotW.value / Math.max(1, n.value - 1)
  const step = Math.max(1, Math.ceil(widest / Math.max(1, slot)))
  const out: Array<{ text: string; x: number }> = []
  for (let i = 0; i < n.value; i += step) {
    const half = widths[i] / 2
    out.push({ text: short[i], x: Math.min(Math.max(x(i), half + 2), width.value - half - 2) })
  }
  return out
})

/** Splits a series into runs of consecutive values, so a gap breaks the line. */
function runs(row: Array<number | null>): Array<Array<{ i: number; v: number }>> {
  const out: Array<Array<{ i: number; v: number }>> = []
  let cur: Array<{ i: number; v: number }> = []
  row.forEach((v, i) => {
    if (v === null) {
      if (cur.length) out.push(cur)
      cur = []
    } else {
      cur.push({ i, v })
    }
  })
  if (cur.length) out.push(cur)
  return out
}

const fmt = (v: number): string => (Math.round(v * 100) / 100).toString()

const lines = computed(() =>
  tops.value.map((row, si) => {
    const segs = runs(row)
    const d = segs
      .map((seg) => seg.map((p, k) => `${k === 0 ? 'M' : 'L'}${fmt(x(p.i))} ${fmt(y(p.v))}`).join(' '))
      .join(' ')
    // Overlaid areas close to the baseline; stacked ones to the series below.
    const area =
      props.kind === 'area'
        ? segs
            .map((seg) => {
              const top = seg.map((p, k) => `${k === 0 ? 'M' : 'L'}${fmt(x(p.i))} ${fmt(y(p.v))}`).join(' ')
              const below = [...seg]
                .reverse()
                .map((p) => {
                  const under = props.stacked && si > 0 ? (tops.value[si - 1][p.i] ?? 0) : null
                  return `L${fmt(x(p.i))} ${fmt(under === null ? baseline.value : y(under))}`
                })
                .join(' ')
              return `${top} ${below} Z`
            })
            .join(' ')
        : ''
    const points = segs.flat().map((p) => ({ i: p.i, cx: x(p.i), cy: y(p.v) }))
    return { color: props.series[si].color, d, area, points }
  }),
)

const showMarkers = computed(() => n.value <= 24 && plotW.value / Math.max(1, n.value) >= 12)

const bars = computed(() => {
  if (!isBar.value) return []
  const out: Array<{ x: number; y: number; w: number; h: number; color: string; si: number; i: number; value: number }> = []
  const k = Math.max(1, props.series.length)
  if (props.stacked) {
    const w = Math.min(48, band.value * 0.6)
    for (let i = 0; i < n.value; i++) {
      let pos = 0
      let neg = 0
      props.series.forEach((s, si) => {
        const v = s.data[i]
        if (v === null || v === undefined || v === 0) return
        const from = v > 0 ? pos : neg
        const to = from + v
        if (v > 0) pos = to
        else neg = to
        const top = y(Math.max(from, to))
        out.push({ x: x(i) - w / 2, y: top, w, h: Math.max(0, y(Math.min(from, to)) - top), color: s.color, si, i, value: v })
      })
    }
    return out
  }
  const group = Math.min(band.value * 0.72, 40 * k)
  const gap = group / k > 6 ? 2 : 0
  const w = Math.max(1, (group - gap * (k - 1)) / k)
  for (let i = 0; i < n.value; i++) {
    props.series.forEach((s, si) => {
      const v = s.data[i]
      if (v === null || v === undefined) return
      const top = Math.min(y(v), baseline.value)
      out.push({
        x: x(i) - group / 2 + si * (w + gap),
        y: top,
        w,
        h: Math.abs(y(v) - baseline.value),
        color: s.color,
        si,
        i,
        value: v,
      })
    })
  }
  return out
})

// --- hover ------------------------------------------------------------------

const hover = ref<number | null>(null)

function onMove(e: PointerEvent | MouseEvent): void {
  const rect = svgEl.value?.getBoundingClientRect()
  if (!rect || n.value === 0) return
  const px = e.clientX - rect.left - padLeft.value
  let i: number
  if (isBar.value) i = Math.floor(px / band.value)
  else i = n.value <= 1 ? 0 : Math.round((px / plotW.value) * (n.value - 1))
  hover.value = Math.min(n.value - 1, Math.max(0, i))
}

function onLeave(): void {
  hover.value = null
}

const tooltip = computed(() => {
  const i = hover.value
  if (i === null) return null
  return {
    x: x(i),
    y: PAD_TOP,
    title: props.labels[i] ?? '',
    rows: props.series.map((s) => ({ label: s.label, color: s.color, value: formatValue(s.data[i] ?? null) })),
  }
})

const legend = computed(() => props.series.map((s) => ({ label: s.label, color: s.color })))
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-chart" :class="[`admin-chart--${kind}`, { 'admin-chart--stacked': stacked }]">
    <header v-if="title || description" class="admin-widget__hd">
      <h3 v-if="title" class="admin-widget__title">{{ title }}</h3>
      <p v-if="description" class="admin-widget__desc">{{ description }}</p>
    </header>
    <div v-if="isEmpty" class="admin-widget__empty">{{ tr('Нет данных за период') }}</div>
    <template v-else>
      <div ref="box" class="admin-chart__box" :style="{ minHeight: `${height}px` }">
        <svg
          ref="svgEl"
          class="admin-chart__svg"
          :width="width"
          :height="boxHeight"
          :viewBox="`0 0 ${width} ${boxHeight}`"
          role="img"
          :aria-label="title || kind"
          @pointermove="onMove"
          @mousemove="onMove"
          @pointerleave="onLeave"
          @mouseleave="onLeave"
        >
          <!-- Gridlines and y ticks -->
          <g class="admin-chart__grid">
            <line
              v-for="(g, idx) in gridLines"
              :key="`g${idx}`"
              :x1="padLeft"
              :x2="padLeft + plotW"
              :y1="g.y"
              :y2="g.y"
              :class="g.zero ? 'admin-chart__axis-line' : 'admin-chart__grid-line'"
            />
            <text
              v-for="(g, idx) in gridLines"
              :key="`t${idx}`"
              :x="padLeft - 6"
              :y="g.y"
              class="admin-chart__tick"
              text-anchor="end"
              dominant-baseline="middle"
            >{{ g.label }}</text>
          </g>

          <!-- Hover: a band behind the bars, a crosshair over lines -->
          <rect
            v-if="hover !== null && isBar"
            class="admin-chart__hover-band"
            :x="padLeft + band * hover"
            :y="PAD_TOP"
            :width="band"
            :height="plotH"
          />

          <g v-if="isBar" class="admin-chart__bars">
            <rect
              v-for="(b, idx) in bars"
              :key="idx"
              class="admin-chart__bar"
              :x="b.x"
              :y="b.y"
              :width="b.w"
              :height="b.h"
              :fill="b.color"
              :rx="stacked ? 0 : Math.min(3, b.w / 2)"
              :data-series="b.si"
              :data-label="labels[b.i]"
              :data-value="b.value"
            />
          </g>

          <g v-else class="admin-chart__series">
            <g v-for="(s, si) in lines" :key="si" :data-series="si">
              <path
                v-if="kind === 'area'"
                class="admin-chart__area"
                :d="s.area"
                :fill="s.color"
                :fill-opacity="stacked ? 0.45 : 0.16"
              />
              <path class="admin-chart__line" :d="s.d" :stroke="s.color" fill="none" />
              <template v-if="showMarkers">
                <circle
                  v-for="p in s.points"
                  :key="p.i"
                  class="admin-chart__point"
                  :cx="p.cx"
                  :cy="p.cy"
                  :r="hover === p.i ? 4.5 : 3"
                  :fill="s.color"
                />
              </template>
              <template v-else>
                <circle
                  v-for="p in s.points.filter((pt) => pt.i === hover)"
                  :key="p.i"
                  class="admin-chart__point"
                  :cx="p.cx"
                  :cy="p.cy"
                  r="4.5"
                  :fill="s.color"
                />
              </template>
            </g>
          </g>

          <line
            v-if="hover !== null && !isBar"
            class="admin-chart__crosshair"
            :x1="x(hover)"
            :x2="x(hover)"
            :y1="PAD_TOP"
            :y2="PAD_TOP + plotH"
          />

          <!-- X labels -->
          <text
            v-for="(l, idx) in xLabels"
            :key="`x${idx}`"
            class="admin-chart__tick"
            :x="l.x"
            :y="PAD_TOP + plotH + 15"
            text-anchor="middle"
          >{{ l.text }}</text>
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
            <td v-for="(s, si) in series" :key="si">{{ formatValue(s.data[i] ?? null) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </UidCard>
</template>

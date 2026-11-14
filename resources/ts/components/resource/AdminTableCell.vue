<script setup lang="ts">
/**
 * AdminTableCell — one cell of a table column by its TableColumn preset, the
 * same everywhere a column is drawn: a resource list, an embedded resource
 * table, a RelationTable field and a TableWidget.
 *
 *   - image → a thumbnail (TableColumn::asImage, meta.width / meta.height)
 *   - badge → a UidBadge, its tone from meta.colors and its caption from
 *             meta.labels (TableColumn::asBadge)
 *   - link  → a link by meta.template (TableColumn::asLink); plain text when
 *             the template does not resolve against the row
 *   - audio → the kit's mini audio player (TableColumn::asAudio); one row
 *             plays at a time
 *   - video → a poster or a tile with a play button; the player opens in a
 *             modal (TableColumn::asVideo)
 *   - progress → a bar with its per cent (TableColumn::asProgress, 1.75)
 *   - the rest goes through formatCell: dates, money, booleans, sizes, text
 *
 * `value` is the raw value; `row` is the raw row the link template reads.
 * A cell's tone (TableColumn::tone(), or a `{value, tone}` cell, 1.75)
 * colours its text, its badge or its bar; `column` names the column whose
 * tone the row carries.
 */
import { openInDrawer } from '../../composables/useActionRunner'
import { computed } from 'vue'
import { UidAudio, UidBadge, UidImage, UidLink } from '@dskripchenko/ui'
import { badgeTone, cellTone, cellValue, formatCell, progressPercent, resolveLinkHref, type CellMeta } from './cellFormat'
import { badgeVariant, resolveTone, toneToken } from '../tones'
import VideoCell from '../media/VideoCell.vue'
import { toAudioTracks, type MediaDisk } from '../media/mediaSource'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  value?: unknown
  preset?: string | null
  meta?: CellMeta | null
  row?: Record<string, unknown> | null
  /** The column's name: the key of its tone in the row's `__tones`. */
  column?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  value: undefined,
  preset: null,
  meta: null,
  row: null,
  column: null,
})

const meta = computed<CellMeta>(() => props.meta ?? {})
/** The value without a `{value, tone}` envelope. */
const raw = computed<unknown>(() => cellValue(props.value))
const tone = computed(() => cellTone(props.value, props.row, props.column))
const text = computed<string>(() => formatCell(raw.value, props.preset ?? undefined, meta.value))

/** The bar of a progress cell: its width, 0..100 %, and its colour. */
const progress = computed<{ width: number, color: string } | null>(() => {
  if (props.preset !== 'progress') return null
  const percent = progressPercent(raw.value, meta.value)
  if (percent === null) return null
  const own = tone.value ?? resolveTone(typeof meta.value.tone === 'string' ? meta.value.tone : null)
  return {
    width: Math.max(0, Math.min(100, percent)),
    color: own ? toneToken(own) : 'var(--uid-accent)',
  }
})

const imageSrc = computed<string>(() => {
  if (props.preset !== 'image') return ''
  const v = raw.value
  if (typeof v === 'string') return v
  // A media attachment may arrive as an object carrying its URL.
  if (v && typeof v === 'object') {
    const o = v as Record<string, unknown>
    const url = o.thumb_url ?? o.thumbnail_url ?? o.url ?? o.src
    return typeof url === 'string' ? url : ''
  }
  return ''
})

function size(v: unknown, fallback: number): number {
  const n = typeof v === 'number' ? v : Number(v)
  return Number.isFinite(n) && n > 0 ? n : fallback
}
const imageWidth = computed<number>(() => size(meta.value.width, props.preset === 'video' ? 64 : 32))
const imageHeight = computed<number>(() => size(meta.value.height, imageWidth.value))

const mediaDisk = computed<MediaDisk | null>(() => (meta.value.disk as MediaDisk | undefined) ?? null)
const audioTrack = computed(() =>
  props.preset === 'audio' ? toAudioTracks(raw.value, mediaDisk.value)[0] ?? null : null,
)

const href = computed<string>(() =>
  props.preset === 'link'
    ? resolveLinkHref(meta.value.template as string | undefined, props.row ?? {}, raw.value)
    : '',
)
const target = computed<string | undefined>(() => (meta.value.target as string | undefined) || undefined)

/** `asLink($template, 'drawer')`: the list or the screen opens in the side panel over the page. */
function onLinkClick(event: MouseEvent): void {
  if (target.value !== 'drawer' || event.ctrlKey || event.metaKey || event.shiftKey) return
  event.preventDefault()
  openInDrawer(href.value, { title: text.value })
}
</script>

<template>
  <span v-if="preset === 'image'" class="admin-cell-image">
    <UidImage
      v-if="imageSrc"
      :src="imageSrc"
      alt=""
      :width="imageWidth"
      :height="imageHeight"
      fit="cover"
      radius="sm"
    />
  </span>
  <!-- A click on a player is not a click on the row: it would open the record. -->
  <span v-else-if="preset === 'audio' && audioTrack" class="admin-cell-media" @click.stop>
    <UidAudio
      :src="audioTrack.src"
      :type="audioTrack.type"
      :peaks="audioTrack.peaks"
      :waveform="meta.waveform === true"
      variant="mini"
    />
  </span>
  <VideoCell
    v-else-if="preset === 'video'"
    :value="raw"
    :row="row"
    :disk="mediaDisk"
    :poster="(meta.poster as string | undefined) ?? null"
    :width="imageWidth"
    :height="size(meta.height, Math.round(imageWidth * 9 / 16))"
  />
  <span
    v-else-if="progress"
    class="admin-cell-progress"
    role="progressbar"
    :aria-valuenow="progress.width"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="text"
    data-testid="cell-progress"
  >
    <span class="admin-cell-progress__track">
      <span class="admin-cell-progress__fill" :style="{ width: `${progress.width}%`, background: progress.color }" />
    </span>
    <span class="admin-cell-progress__label">{{ text }}</span>
  </span>
  <UidBadge
    v-else-if="preset === 'badge' && text !== ''"
    :variant="tone ? badgeVariant(tone) : badgeTone(raw, meta)"
  >{{ text }}</UidBadge>
  <UidLink
    v-else-if="preset === 'link' && href"
    class="admin-cell-truncate"
    :href="href"
    :external="target === '_blank'"
    :title="text"
    data-testid="cell-link"
    @click.stop="onLinkClick"
  >{{ text }}</UidLink>
  <span
    v-else-if="text === ''"
    class="admin-cell-empty"
    :aria-label="tr('Нет значения')"
  >—</span>
  <span
    v-else
    class="admin-cell-truncate"
    :class="tone ? `admin-cell--tone-${tone}` : undefined"
    :data-tone="tone ?? undefined"
    :title="text"
  >{{ text }}</span>
</template>

<style>
/*
 * One line with an ellipsis and a sane max-width, 320px by default
 * (--admin-cell-max-width); the full value is in the native tooltip, `title`.
 */
.admin-cell-truncate {
  display: inline-block;
  max-width: var(--admin-cell-max-width, 320px);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.admin-cell-truncate--multi {
  display: -webkit-box;
  white-space: normal;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  word-break: break-word;
}
/* No value: a quiet dash, so an empty cell reads as empty rather than broken. */
.admin-cell-empty {
  color: var(--uid-text-tertiary);
}
.admin-cell-media {
  display: inline-flex;
  vertical-align: middle;
  max-width: 100%;
}
.admin-cell-image {
  display: inline-flex;
  vertical-align: middle;
}
/* A cell's tone (TableColumn::tone(), 1.75): the text in the tone's colour. */
.admin-cell--tone-success { color: var(--uid-color-success-text, var(--uid-color-success)); }
.admin-cell--tone-danger { color: var(--uid-color-danger-text, var(--uid-color-danger)); }
.admin-cell--tone-warning { color: var(--uid-color-warning-text, var(--uid-color-warning)); }
.admin-cell--tone-info { color: var(--uid-color-info-text, var(--uid-color-info)); }
.admin-cell--tone-primary { color: var(--uid-accent); }
.admin-cell--tone-neutral { color: var(--uid-text-secondary); }
/* A share as a bar with its caption (TableColumn::asProgress, 1.75). */
.admin-cell-progress {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs, 6px);
  width: 100%;
  min-width: 96px;
  vertical-align: middle;
}
.admin-cell-progress__track {
  position: relative;
  flex: 1 1 auto;
  height: 8px;
  border-radius: 4px;
  background: var(--uid-color-zinc-100, rgba(0, 0, 0, 0.06));
  overflow: hidden;
}
.admin-cell-progress__fill {
  position: absolute;
  inset: 0 auto 0 0;
  border-radius: 4px;
}
.admin-cell-progress__label {
  flex: 0 0 auto;
  min-width: 3.5em;
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-text-secondary);
}
</style>

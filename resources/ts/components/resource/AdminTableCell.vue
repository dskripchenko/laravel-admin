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
 *   - the rest goes through formatCell: dates, money, booleans, sizes, text
 *
 * `value` is the raw value; `row` is the raw row the link template reads.
 */
import { computed } from 'vue'
import { UidBadge, UidImage, UidLink } from '@dskripchenko/ui'
import { badgeTone, formatCell, resolveLinkHref, type CellMeta } from './cellFormat'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  value?: unknown
  preset?: string | null
  meta?: CellMeta | null
  row?: Record<string, unknown> | null
}

const props = withDefaults(defineProps<Props>(), {
  value: undefined,
  preset: null,
  meta: null,
  row: null,
})

const meta = computed<CellMeta>(() => props.meta ?? {})
const text = computed<string>(() => formatCell(props.value, props.preset ?? undefined, meta.value))

const imageSrc = computed<string>(() => {
  if (props.preset !== 'image') return ''
  const v = props.value
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
const imageWidth = computed<number>(() => size(meta.value.width, 32))
const imageHeight = computed<number>(() => size(meta.value.height, imageWidth.value))

const href = computed<string>(() =>
  props.preset === 'link'
    ? resolveLinkHref(meta.value.template as string | undefined, props.row ?? {}, props.value)
    : '',
)
const target = computed<string | undefined>(() => (meta.value.target as string | undefined) || undefined)
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
  <UidBadge
    v-else-if="preset === 'badge' && text !== ''"
    :variant="badgeTone(value, meta)"
  >{{ text }}</UidBadge>
  <UidLink
    v-else-if="preset === 'link' && href"
    class="admin-cell-truncate"
    :href="href"
    :external="target === '_blank'"
    :title="text"
    @click.stop
  >{{ text }}</UidLink>
  <span
    v-else-if="text === ''"
    class="admin-cell-empty"
    :aria-label="tr('Нет значения')"
  >—</span>
  <span
    v-else
    class="admin-cell-truncate"
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
.admin-cell-image {
  display: inline-flex;
  vertical-align: middle;
}
</style>

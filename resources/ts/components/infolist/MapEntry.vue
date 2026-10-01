<script setup lang="ts">
/**
 * MapEntry — a point by latitude and longitude (the backend's
 * Infolist\MapEntry). No map tiles are loaded: the coordinates are shown with
 * a link that opens the point on OpenStreetMap at `->zoom()`.
 *
 * The coordinates come from `->latColumn()`/`->lngColumn()` of the record, or
 * from the entry's own value: `{lat, lng}` (also lat/lon, latitude/longitude),
 * `[lat, lng]` or a "lat,lng" string.
 */
import { computed } from 'vue'
import { MapPin } from 'lucide-vue-next'
import { UidIcon, UidLink } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'
import { useEntryValue } from './entryValue'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name?: string
  value?: unknown
  latColumn?: string | null
  lngColumn?: string | null
  zoom?: number
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  latColumn: null,
  lngColumn: null,
  zoom: 15,
  placeholder: '—',
})

const record = tryUseRecord()
const raw = useEntryValue(props)

const num = (v: unknown): number | null => {
  if (v === null || v === undefined || v === '') return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

const point = computed<{ lat: number; lng: number } | null>(() => {
  let lat: number | null = null
  let lng: number | null = null
  if ((props.latColumn || props.lngColumn) && record) {
    lat = num(record[props.latColumn ?? 'lat'])
    lng = num(record[props.lngColumn ?? 'lng'])
  } else {
    // Without a value of its own, the record's conventional columns.
    const v = raw.value ?? (record ? { lat: record.lat ?? record.latitude, lng: record.lng ?? record.lon ?? record.longitude } : undefined)
    if (Array.isArray(v)) {
      lat = num(v[0])
      lng = num(v[1])
    } else if (typeof v === 'string') {
      const [a, b] = v.split(/[,;\s]+/)
      lat = num(a)
      lng = num(b)
    } else if (v && typeof v === 'object') {
      const r = v as Record<string, unknown>
      lat = num(r.lat ?? r.latitude)
      lng = num(r.lng ?? r.lon ?? r.longitude)
    }
  }
  if (lat === null || lng === null || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null
  return { lat, lng }
})

const text = computed<string>(() =>
  point.value ? `${point.value.lat.toFixed(6)}, ${point.value.lng.toFixed(6)}` : props.placeholder,
)
const href = computed<string | null>(() => {
  const p = point.value
  if (!p) return null
  const z = Math.min(19, Math.max(1, Math.round(props.zoom)))
  return `https://www.openstreetmap.org/?mlat=${p.lat}&mlon=${p.lng}#map=${z}/${p.lat}/${p.lng}`
})
</script>

<template>
  <span class="admin-infolist-map">
    <UidIcon v-if="point" :icon="MapPin" :size="14" aria-hidden="true" />
    <span class="admin-infolist-map__coords">{{ text }}</span>
    <UidLink v-if="href" :href="href" external class="admin-infolist-map__link">
      {{ tr('Открыть на карте') }}
    </UidLink>
  </span>
</template>

<style>
.admin-infolist-map {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--uid-space-xs, 6px);
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-primary);
}
.admin-infolist-map__coords {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
}
</style>

<script setup lang="ts">
/**
 * VideoEntry — a video of the record (the backend's Infolist\VideoEntry),
 * played by the kit's UidVideo.
 *
 * The value is a URL, a storage path (resolved through `disk`), an uploaded
 * file (`{url, mime, …}`), a list of sources or a `{sources, poster, tracks}`
 * object. `posterFrom` names another attribute of the record holding the
 * poster.
 */
import { computed } from 'vue'
import { UidVideo } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import { tryUseRecord } from './recordContext'
import { cssSize, mediaUrlOf, resumeKeyOf, toVideoConfig, type MediaDisk } from '../media/mediaSource'

interface Props {
  name?: string
  label?: string
  value?: unknown
  disk?: MediaDisk | null
  poster?: string | null
  posterFrom?: string | null
  aspectRatio?: string | null
  maxWidth?: string | number | null
  resume?: boolean
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  disk: null,
  poster: null,
  posterFrom: null,
  aspectRatio: null,
  maxWidth: '640px',
  resume: false,
  placeholder: '—',
})

const raw = useEntryValue(props)
const record = tryUseRecord()
const config = computed(() => toVideoConfig(raw.value, props.disk))
const poster = computed<string | undefined>(() => {
  const fromRecord = props.posterFrom && record ? mediaUrlOf(record[props.posterFrom], props.disk) : null
  return fromRecord ?? mediaUrlOf(props.poster, props.disk) ?? config.value.poster
})
const resumeKey = computed(() => resumeKeyOf(props.resume, config.value.sources[0]?.src))
</script>

<template>
  <span v-if="config.sources.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <div v-else class="admin-infolist-media" :style="{ maxWidth: cssSize(maxWidth) }">
    <UidVideo
      :sources="config.sources"
      :poster="poster"
      :tracks="config.tracks"
      :thumbnails="config.thumbnails"
      :title="label || name"
      :aspect-ratio="aspectRatio ?? undefined"
      :resume-key="resumeKey"
    />
  </div>
</template>

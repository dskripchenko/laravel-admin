<script setup lang="ts">
/**
 * AudioEntry — an audio recording of the record (the backend's
 * Infolist\AudioEntry), played by the kit's UidAudio: the one-line mini
 * player unless `variant` is `default`.
 *
 * The value is a URL, a storage path (resolved through `disk`), an uploaded
 * file (`{url, mime, name, …}`) or a list of them — then a playlist, always
 * in the full player.
 */
import { computed } from 'vue'
import { UidAudio } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import { tryUseRecord } from './recordContext'
import { resumeKeyOf, toAudioTracks, type MediaDisk } from '../media/mediaSource'

interface Props {
  name?: string
  label?: string
  value?: unknown
  disk?: MediaDisk | null
  variant?: 'default' | 'mini'
  waveform?: boolean
  peaksFrom?: string | null
  resume?: boolean
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  disk: null,
  variant: 'mini',
  waveform: false,
  peaksFrom: null,
  resume: false,
  placeholder: '—',
})

const raw = useEntryValue(props)
const record = tryUseRecord()
const tracks = computed(() => toAudioTracks(raw.value, props.disk))
const peaks = computed<number[] | undefined>(() => {
  const v = props.peaksFrom && record ? record[props.peaksFrom] : null
  return Array.isArray(v) ? v.filter((p): p is number => typeof p === 'number') : undefined
})
const resumeKey = computed(() => resumeKeyOf(props.resume, tracks.value[0]?.src))
</script>

<template>
  <span v-if="tracks.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <div
    v-else
    class="admin-infolist-media"
    :class="{ 'admin-infolist-media--mini': variant === 'mini' && tracks.length === 1 }"
  >
    <UidAudio
      v-if="tracks.length === 1"
      :src="tracks[0].src"
      :type="tracks[0].type"
      :title="variant === 'mini' ? undefined : tracks[0].title"
      :artist="variant === 'mini' ? undefined : tracks[0].artist"
      :peaks="peaks ?? tracks[0].peaks"
      :variant="variant"
      :waveform="waveform"
      :resume-key="resumeKey"
    />
    <UidAudio v-else :tracks="tracks" :waveform="waveform" :resume-key="resumeKey" />
  </div>
</template>

<script setup lang="ts">
/**
 * The audio layout — `Layout::audio(...)`: the kit's UidAudio, a single
 * track or a playlist, with a waveform or as the one-line mini player.
 *
 * The tracks come resolved from the server; with `source` (a state key) they
 * are read from the screen's form state or the record — a URL, a storage path
 * resolved through `disk`, an uploaded file, or a list of them.
 */
import { computed, ref, watch } from 'vue'
import { UidAudio, type UidAudioTrack } from '@dskripchenko/ui'
import { tryUseFormState } from '../render/formState'
import { tryUseRecord } from '../infolist/recordContext'
import { cssSize, resumeKeyOf, toAudioTracks, type MediaDisk } from '../media/mediaSource'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  tracks?: UidAudioTrack[]
  source?: string | null
  disk?: MediaDisk | null
  title?: string | null
  artist?: string | null
  variant?: 'default' | 'mini'
  waveform?: boolean
  peaks?: number[] | null
  loop?: boolean
  autoAdvance?: boolean
  preload?: 'none' | 'metadata' | 'auto'
  crossorigin?: '' | 'anonymous' | 'use-credentials' | null
  exclusive?: boolean
  hotkeys?: boolean
  rates?: number[] | null
  resumeKey?: string | boolean | null
  showVolume?: boolean
  showRate?: boolean
  showPlaylist?: boolean
  maxWidth?: string | number | null
  hlsOptions?: Record<string, unknown> | null
}

const props = withDefaults(defineProps<Props>(), {
  tracks: () => [],
  source: null,
  disk: null,
  title: null,
  artist: null,
  variant: 'default',
  waveform: false,
  peaks: null,
  loop: false,
  autoAdvance: true,
  preload: 'metadata',
  crossorigin: null,
  exclusive: true,
  hotkeys: true,
  rates: null,
  resumeKey: null,
  showVolume: true,
  showRate: true,
  showPlaylist: true,
  maxWidth: null,
  hlsOptions: null,
})

const form = tryUseFormState()
const record = tryUseRecord()

function read(key: string): unknown {
  const fromForm = form?.getField(key)
  if (fromForm !== undefined) return fromForm
  return record ? record[key] : undefined
}

const list = computed<UidAudioTrack[]>(() =>
  props.source ? toAudioTracks(read(props.source), props.disk) : props.tracks,
)
const single = computed<UidAudioTrack | null>(() => (list.value.length === 1 ? list.value[0] : null))
const index = ref(0)
watch(() => list.value.length, (n) => {
  if (index.value >= n) index.value = 0
})

const resume = computed(() => resumeKeyOf(props.resumeKey, list.value[0]?.src))
const boxStyle = computed(() => ({ maxWidth: cssSize(props.maxWidth) }))
const playerKey = computed(() => list.value.map((t) => t.src).join('|'))
</script>

<template>
  <div class="admin-media-layout admin-audio-layout" :style="boxStyle">
    <UidAudio
      v-if="single"
      :key="playerKey"
      :src="single.src"
      :type="single.type"
      :title="title || single.title"
      :artist="artist || single.artist"
      :peaks="peaks ?? single.peaks"
      :variant="variant"
      :waveform="waveform"
      :loop="loop"
      :preload="preload"
      :crossorigin="crossorigin ?? undefined"
      :exclusive="exclusive"
      :hotkeys="hotkeys"
      :rates="rates ?? undefined"
      :resume-key="resume"
      :show-volume="showVolume"
      :show-rate="showRate"
      :hls-options="hlsOptions ?? undefined"
    />
    <UidAudio
      v-else-if="list.length > 1"
      :key="playerKey"
      v-model:index="index"
      :tracks="list"
      :variant="variant"
      :waveform="waveform"
      :loop="loop"
      :auto-advance="autoAdvance"
      :preload="preload"
      :crossorigin="crossorigin ?? undefined"
      :exclusive="exclusive"
      :hotkeys="hotkeys"
      :rates="rates ?? undefined"
      :resume-key="resume"
      :show-volume="showVolume"
      :show-rate="showRate"
      :show-playlist="showPlaylist"
      :hls-options="hlsOptions ?? undefined"
    />
    <span v-else class="admin-media-layout__none">{{ tr('Нет аудио') }}</span>
  </div>
</template>

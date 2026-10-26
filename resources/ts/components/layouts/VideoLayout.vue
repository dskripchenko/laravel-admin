<script setup lang="ts">
/**
 * The video layout — `Layout::video(...)`: the kit's UidVideo.
 *
 * The sources, poster and WebVTT tracks come resolved from the server; with
 * `source` (a state key) they are read from the screen's form state or the
 * record instead — a URL, a storage path resolved through `disk`, an
 * uploaded file, a list of sources, or a whole `{sources, poster, tracks,
 * thumbnails}` object.
 *
 * HLS sources play through the kit's adapter, which the kit loads by itself
 * on first use. Starting a player pauses the others on the page.
 */
import { computed } from 'vue'
import { UidVideo, type UidMediaSource, type UidMediaTrack } from '@dskripchenko/ui'
import { tryUseFormState } from '../render/formState'
import { tryUseRecord } from '../infolist/recordContext'
import { cssSize, resumeKeyOf, toVideoConfig, type MediaDisk } from '../media/mediaSource'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  sources?: UidMediaSource[]
  tracks?: UidMediaTrack[]
  poster?: string | null
  thumbnails?: string | null
  source?: string | null
  disk?: MediaDisk | null
  title?: string | null
  autoplay?: boolean
  muted?: boolean
  loop?: boolean
  controls?: boolean
  preload?: 'none' | 'metadata' | 'auto'
  crossorigin?: '' | 'anonymous' | 'use-credentials' | null
  exclusive?: boolean
  hotkeys?: boolean
  rates?: number[] | null
  resumeKey?: string | boolean | null
  aspectRatio?: string | null
  maxWidth?: string | number | null
  autoHide?: number | null
  hlsOptions?: Record<string, unknown> | null
}

const props = withDefaults(defineProps<Props>(), {
  sources: () => [],
  tracks: () => [],
  poster: null,
  thumbnails: null,
  source: null,
  disk: null,
  title: null,
  autoplay: false,
  muted: false,
  loop: false,
  controls: true,
  preload: 'metadata',
  crossorigin: null,
  exclusive: true,
  hotkeys: true,
  rates: null,
  resumeKey: null,
  aspectRatio: null,
  maxWidth: null,
  autoHide: null,
  hlsOptions: null,
})

const form = tryUseFormState()
const record = tryUseRecord()

/** A key of the screen's state: the form's working copy, else the record. */
function read(key: string): unknown {
  const fromForm = form?.getField(key)
  if (fromForm !== undefined) return fromForm
  return record ? record[key] : undefined
}

const config = computed(() => {
  if (!props.source) {
    return {
      sources: props.sources,
      tracks: props.tracks,
      poster: props.poster ?? undefined,
      thumbnails: props.thumbnails ?? undefined,
      title: undefined as string | undefined,
    }
  }
  const fromState = toVideoConfig(read(props.source), props.disk)
  return {
    ...fromState,
    // What the layout states wins over what the value carries.
    tracks: props.tracks.length > 0 ? props.tracks : fromState.tracks,
    poster: props.poster ?? fromState.poster,
    thumbnails: props.thumbnails ?? fromState.thumbnails,
  }
})

const caption = computed(() => props.title || config.value.title || '')
const resume = computed(() => resumeKeyOf(props.resumeKey, config.value.sources[0]?.src))
const boxStyle = computed(() => ({ maxWidth: cssSize(props.maxWidth) }))
// A new source list remounts the player, so it never mixes two videos.
const playerKey = computed(() => config.value.sources.map((s) => s.src).join('|'))
</script>

<template>
  <figure class="admin-media-layout admin-video-layout" :style="boxStyle">
    <figcaption v-if="caption" class="admin-media-layout__title">{{ caption }}</figcaption>
    <UidVideo
      v-if="config.sources.length > 0"
      :key="playerKey"
      :sources="config.sources"
      :poster="config.poster"
      :tracks="config.tracks"
      :thumbnails="config.thumbnails"
      :title="caption || undefined"
      :autoplay="autoplay"
      :muted="muted || autoplay"
      :loop="loop"
      :controls="controls"
      :preload="preload"
      :crossorigin="crossorigin ?? undefined"
      :exclusive="exclusive"
      :hotkeys="hotkeys"
      :rates="rates ?? undefined"
      :resume-key="resume"
      :aspect-ratio="aspectRatio ?? undefined"
      :auto-hide="autoHide ?? undefined"
      :hls-options="hlsOptions ?? undefined"
    />
    <div v-else class="admin-media-layout__empty" :style="{ aspectRatio: aspectRatio ?? '16 / 9' }">
      {{ tr('Нет видео') }}
    </div>
  </figure>
</template>

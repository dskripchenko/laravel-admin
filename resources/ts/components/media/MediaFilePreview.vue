<script setup lang="ts">
/**
 * MediaFilePreview — the preview of one file: a player for audio and video,
 * the picture for an image, nothing for anything else (the caller shows the
 * file's name and link).
 *
 * The file is what an upload puts in the state, `{url, mime, name, …}`, or a
 * plain URL; its kind comes from the MIME type, else from the extension.
 * Used by the file field under its upload and by the view of a file.
 */
import { computed } from 'vue'
import { UidAudio, UidVideo } from '@dskripchenko/ui'
import { mediaKind, mediaMimeOf, mediaUrlOf, toAudioTracks, toVideoSources } from './mediaSource'

interface Props {
  file: unknown
  /** The widest the video gets. */
  maxWidth?: string
}

const props = withDefaults(defineProps<Props>(), { maxWidth: '480px' })

const url = computed(() => mediaUrlOf(props.file))
const kind = computed(() => (url.value ? mediaKind(mediaMimeOf(props.file), url.value) : null))
const name = computed<string>(() => {
  const f = props.file
  return f && typeof f === 'object' && typeof (f as Record<string, unknown>).name === 'string'
    ? (f as Record<string, string>).name
    : ''
})
const audio = computed(() => (kind.value === 'audio' ? toAudioTracks(props.file)[0] ?? null : null))
const sources = computed(() => (kind.value === 'video' ? toVideoSources(props.file) : []))
</script>

<template>
  <div v-if="kind === 'video' && sources.length > 0" class="admin-media-preview" :style="{ maxWidth }">
    <UidVideo :sources="sources" :title="name || undefined" preload="metadata" />
  </div>
  <div v-else-if="audio" class="admin-media-preview admin-media-preview--audio">
    <UidAudio :src="audio.src" :type="audio.type" :show-rate="false" />
  </div>
  <div v-else-if="kind === 'image' && url" class="admin-media-preview">
    <img :src="url" :alt="name" class="admin-media-preview__img" loading="lazy">
  </div>
</template>

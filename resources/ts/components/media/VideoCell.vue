<script setup lang="ts">
/**
 * VideoCell — a video in a table cell (TableColumn::asVideo): the poster, or
 * a plain tile, with a play button. Nothing is downloaded until the click,
 * which opens the kit's UidVideo in a modal and starts it.
 */
import { computed, nextTick, ref } from 'vue'
import { Play } from 'lucide-vue-next'
import { UidIcon, UidModal, UidVideo } from '@dskripchenko/ui'
import { mediaUrlOf, toVideoConfig, type MediaDisk } from './mediaSource'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  value?: unknown
  /** The row: the poster column is read from it. */
  row?: Record<string, unknown> | null
  disk?: MediaDisk | null
  /** The row's column holding the poster. */
  poster?: string | null
  width?: number
  height?: number
  /** The modal's title — the column's label or the row's name. */
  title?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  value: undefined,
  row: null,
  disk: null,
  poster: null,
  width: 64,
  height: 36,
  title: null,
})

const config = computed(() => toVideoConfig(props.value, props.disk))
const posterUrl = computed<string | undefined>(() => {
  const fromRow = props.poster && props.row ? mediaUrlOf(props.row[props.poster], props.disk) : null
  if (fromRow) return fromRow
  if (config.value.poster) return config.value.poster
  // A media attachment carries its own thumbnail.
  const v = props.value
  if (v && typeof v === 'object' && !Array.isArray(v)) {
    const o = v as Record<string, unknown>
    return mediaUrlOf(o.thumb_url ?? o.thumbnail_url ?? o.poster_url, props.disk) ?? undefined
  }
  return undefined
})

const open = ref(false)
const player = ref<InstanceType<typeof UidVideo> | null>(null)

async function show(): Promise<void> {
  open.value = true
  await nextTick()
  // Still inside the click's user activation: the video may start with sound.
  void player.value?.play().catch(() => undefined)
}
</script>

<template>
  <span v-if="config.sources.length > 0" class="admin-cell-video">
    <button
      type="button"
      class="admin-cell-video__thumb"
      :style="{ width: `${width}px`, height: `${height}px` }"
      :aria-label="tr('Смотреть видео')"
      :title="tr('Смотреть видео')"
      @click.stop="show"
    >
      <img v-if="posterUrl" :src="posterUrl" alt="" loading="lazy" class="admin-cell-video__img">
      <span class="admin-cell-video__play"><UidIcon :icon="Play" :size="14" /></span>
    </button>
    <UidModal v-model="open" size="lg" :title="title || undefined">
      <UidVideo
        v-if="open"
        ref="player"
        :sources="config.sources"
        :poster="posterUrl"
        :tracks="config.tracks"
        :thumbnails="config.thumbnails"
        :title="title || undefined"
      />
    </UidModal>
  </span>
  <span v-else class="admin-cell-empty" :aria-label="tr('Нет значения')">—</span>
</template>

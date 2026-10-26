<script setup lang="ts">
/**
 * FileEntry — the view of a file field: the file's name as a link, and a
 * player for audio and video (an image shows its picture).
 *
 * The value is what the upload stored, `{url, name, mime, size, …}`, a URL,
 * or a list of either.
 */
import { computed } from 'vue'
import { UidLink } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import MediaFilePreview from '../media/MediaFilePreview.vue'
import { mediaUrlOf } from '../media/mediaSource'

interface Props {
  name?: string
  label?: string
  value?: unknown
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  placeholder: '—',
})

const raw = useEntryValue(props)

interface ShownFile { url: string; name: string; file: unknown }

const files = computed<ShownFile[]>(() => {
  const v = raw.value
  const list = Array.isArray(v) ? v : v === null || v === undefined || v === '' ? [] : [v]
  const out: ShownFile[] = []
  for (const file of list) {
    const url = mediaUrlOf(file)
    if (!url) continue
    const o = file && typeof file === 'object' ? file as Record<string, unknown> : {}
    const name = typeof o.name === 'string' && o.name !== ''
      ? o.name
      : decodeURIComponent(url.replace(/[?#].*$/, '').split('/').pop() ?? url)
    out.push({ url, name, file })
  }
  return out
})
</script>

<template>
  <span v-if="files.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <div v-else class="admin-infolist-files">
    <div v-for="f in files" :key="f.url" class="admin-infolist-files__item">
      <UidLink :href="f.url" external class="admin-infolist-files__link">{{ f.name }}</UidLink>
      <MediaFilePreview :file="f.file" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * ImageEntry — an image preview (the backend's Infolist\ImageEntry).
 * `->size(w, h)` sets the thumbnail's box, `->rounded()` makes it round,
 * `->clickToZoom()` opens the full image in a UidModal.
 *
 * The value is a URL, or an object carrying one (`url`, `src`, `path`), or a
 * list of either — then every image is shown.
 */
import { computed, ref } from 'vue'
import { UidModal } from '@dskripchenko/ui'
import { useEntryValue } from './entryValue'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name?: string
  label?: string
  value?: unknown
  width?: number | null
  height?: number | null
  rounded?: boolean
  clickToZoom?: boolean
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  width: null,
  height: null,
  rounded: false,
  clickToZoom: false,
  placeholder: '—',
})

const raw = useEntryValue(props)

function urlOf(v: unknown): string | null {
  if (typeof v === 'string') return v.trim() === '' ? null : v
  if (v && typeof v === 'object') {
    const r = v as Record<string, unknown>
    const u = r.url ?? r.src ?? r.path ?? r.download_url
    return typeof u === 'string' && u !== '' ? u : null
  }
  return null
}

const urls = computed<string[]>(() => {
  const v = raw.value
  const list = Array.isArray(v) ? v : [v]
  return list.map(urlOf).filter((u): u is string => u !== null)
})

const box = computed<Record<string, string>>(() => {
  const w = props.width ?? (props.height ? undefined : 96)
  return {
    ...(w ? { width: `${w}px` } : {}),
    ...(props.height ? { height: `${props.height}px` } : { maxHeight: '160px' }),
  }
})

const zoomed = ref<string | null>(null)
const zoomOpen = computed<boolean>({
  get: () => zoomed.value !== null,
  set: (v) => {
    if (!v) zoomed.value = null
  },
})
</script>

<template>
  <span v-if="urls.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <div v-else class="admin-infolist-image">
    <component
      :is="clickToZoom ? 'button' : 'span'"
      v-for="u in urls"
      :key="u"
      :type="clickToZoom ? 'button' : undefined"
      class="admin-infolist-image__item"
      :class="{ 'admin-infolist-image__item--zoom': clickToZoom }"
      :aria-label="clickToZoom ? tr('Увеличить') : undefined"
      @click="clickToZoom ? (zoomed = u) : undefined"
    >
      <img
        :src="u"
        :alt="label || name"
        loading="lazy"
        class="admin-infolist-image__img"
        :class="{ 'admin-infolist-image__img--rounded': rounded }"
        :style="box"
      >
    </component>
    <UidModal v-if="clickToZoom" v-model="zoomOpen" size="xl" :title="label || undefined">
      <img v-if="zoomed" :src="zoomed" :alt="label || name" class="admin-infolist-image__full">
    </UidModal>
  </div>
</template>

<style>
.admin-infolist-image {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-xs, 6px);
}
.admin-infolist-image__item {
  display: inline-flex;
  padding: 0;
  border: 0;
  background: none;
}
.admin-infolist-image__item--zoom {
  cursor: zoom-in;
}
.admin-infolist-image__item--zoom:focus-visible {
  outline: 2px solid var(--uid-accent);
  outline-offset: 2px;
}
.admin-infolist-image__img {
  display: block;
  object-fit: cover;
  border-radius: var(--uid-radius-md);
  border: 1px solid var(--uid-border-subtle);
}
.admin-infolist-image__img--rounded {
  border-radius: 50%;
}
.admin-infolist-image__full {
  display: block;
  max-width: 100%;
  max-height: 75vh;
  margin: 0 auto;
}
</style>

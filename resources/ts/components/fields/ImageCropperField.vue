<script setup lang="ts">
/**
 * ImageCropperField — a native canvas cropper for the `image_cropper` flavour
 * of a file upload (the backend's `Field\ImageCropper`).
 *
 * Three states:
 *   empty    — a drop zone and a file picker.
 *   picked   — a file has been chosen and is being cropped: a <canvas> with the
 *              picture and a draggable crop rectangle, plus the "Apply" and
 *              "Cancel" buttons.
 *   uploaded — a preview with the name and the mime type, plus "Replace" and
 *              "Remove".
 *
 * How it works:
 *  - On selection: URL.createObjectURL → <img>.onload → scale to fit the
 *    container (500×400 at most) → draw into the canvas.
 *  - The crop rectangle starts out following aspectRatio, when one is set,
 *    inside the picture.
 *  - The mouse: dragging the body moves it, dragging one of the four corners
 *    resizes it with the aspect ratio locked.
 *  - On "Apply": the screen rectangle is mapped onto the source rectangle,
 *    drawImage paints into a new canvas of outputWidth×outputHeight when those
 *    are set, then toBlob and an upload through POST /uploads/image. The answer
 *    goes into the form state.
 *
 * The value's shape in the form state: null | {disk, path, url, name, size, mime}.
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { Image as ImageIcon, Replace, X, Check } from 'lucide-vue-next'
import { UidButton, UidIcon } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { getAdminClient } from '../../stores/registry'
import { adminToast } from '../../stores/toast'
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface UploadedFile {
  disk: string
  path: string
  url: string
  name: string
  size: number
  mime: string
}

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  // The attributes from the backend's Field\ImageCropper, flattened by FieldRenderer
  accept?: string | null
  maxSize?: number | null
  aspectRatio?: number | null
  outputWidth?: number | null
  outputHeight?: number | null
  minCropWidth?: number | null
  minCropHeight?: number | null
  quality?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  accept: null,
  maxSize: null,
  aspectRatio: null,
  outputWidth: null,
  outputHeight: null,
  minCropWidth: 32,
  minCropHeight: 32,
  quality: 0.92,
})

const form = useFormState()
const value = computed<UploadedFile | null>(() => {
  const v = form.getField(props.name)
  return (v && typeof v === 'object' && 'url' in (v as object)) ? (v as UploadedFile) : null
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const fileInput = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)
const uploading = ref(false)

// The "picked" state: the source image plus the crop rectangle in screen space.
const sourceImage = ref<HTMLImageElement | null>(null)
const sourceUrl = ref<string | null>(null)
// scale = screenSize / sourceSize.
const displayScale = ref(1)
const displaySize = ref({ w: 0, h: 0 })
// The crop rectangle in DISPLAY coordinates, from the canvas's top-left 0,0.
const crop = ref({ x: 0, y: 0, w: 100, h: 100 })

const containerMaxW = 500
const containerMaxH = 400

const aspect = computed<number | null>(() => {
  if (props.aspectRatio !== null) return props.aspectRatio
  if (props.outputWidth && props.outputHeight) return props.outputWidth / props.outputHeight
  return null
})

function pickFile(): void {
  fileInput.value?.click()
}

function onFile(e: Event): void {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) loadFile(f)
  ;(e.target as HTMLInputElement).value = ''
}

function onDrop(e: DragEvent): void {
  e.preventDefault()
  dragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (f) loadFile(f)
}

function loadFile(file: File): void {
  if (props.maxSize !== null && file.size > props.maxSize * 1024) {
    adminToast.error(tRaw('Файл больше :size КБ.', { size: props.maxSize }))
    return
  }
  if (!file.type.startsWith('image/')) {
    adminToast.error(tr('Можно только изображения.'))
    return
  }
  cleanupSource()
  sourceUrl.value = URL.createObjectURL(file)
  const img = new Image()
  img.onload = () => {
    sourceImage.value = img
    fitImage()
    initCrop()
  }
  img.src = sourceUrl.value
}

function fitImage(): void {
  if (!sourceImage.value) return
  const iw = sourceImage.value.naturalWidth
  const ih = sourceImage.value.naturalHeight
  const sw = Math.min(containerMaxW / iw, containerMaxH / ih, 1)
  displayScale.value = sw
  displaySize.value = { w: iw * sw, h: ih * sw }
}

function initCrop(): void {
  const { w, h } = displaySize.value
  // By default: the largest rectangle of the given aspect ratio, centred.
  if (aspect.value !== null) {
    let rw = w
    let rh = rw / aspect.value
    if (rh > h) { rh = h; rw = rh * aspect.value }
    crop.value = { x: (w - rw) / 2, y: (h - rh) / 2, w: rw, h: rh }
  } else {
    const pad = Math.min(w, h) * 0.1
    crop.value = { x: pad, y: pad, w: w - 2 * pad, h: h - 2 * pad }
  }
}

// Drag handling
type DragMode = null | 'move' | 'nw' | 'ne' | 'sw' | 'se'
const dragMode = ref<DragMode>(null)
const dragStart = ref<{ mx: number; my: number; cx: number; cy: number; cw: number; ch: number }>({
  mx: 0, my: 0, cx: 0, cy: 0, cw: 0, ch: 0,
})

function startDrag(mode: Exclude<DragMode, null>, e: MouseEvent): void {
  dragMode.value = mode
  dragStart.value = {
    mx: e.clientX, my: e.clientY,
    cx: crop.value.x, cy: crop.value.y, cw: crop.value.w, ch: crop.value.h,
  }
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', endDrag)
  e.preventDefault()
}

function onDrag(e: MouseEvent): void {
  if (dragMode.value === null) return
  const dx = e.clientX - dragStart.value.mx
  const dy = e.clientY - dragStart.value.my
  const s = dragStart.value
  const ds = displaySize.value
  const minW = (props.minCropWidth ?? 32) * displayScale.value
  const minH = (props.minCropHeight ?? 32) * displayScale.value

  if (dragMode.value === 'move') {
    let nx = Math.max(0, Math.min(s.cx + dx, ds.w - s.cw))
    let ny = Math.max(0, Math.min(s.cy + dy, ds.h - s.ch))
    crop.value = { x: nx, y: ny, w: s.cw, h: s.ch }
    return
  }

  // Resizing: the new bounds follow from which corner is being dragged.
  let l = s.cx, t = s.cy, r = s.cx + s.cw, b = s.cy + s.ch
  if (dragMode.value === 'nw') { l = s.cx + dx; t = s.cy + dy }
  if (dragMode.value === 'ne') { r = s.cx + s.cw + dx; t = s.cy + dy }
  if (dragMode.value === 'sw') { l = s.cx + dx; b = s.cy + s.ch + dy }
  if (dragMode.value === 'se') { r = s.cx + s.cw + dx; b = s.cy + s.ch + dy }

  l = Math.max(0, Math.min(l, r - minW))
  t = Math.max(0, Math.min(t, b - minH))
  r = Math.min(ds.w, Math.max(r, l + minW))
  b = Math.min(ds.h, Math.max(b, t + minH))

  let nw = r - l
  let nh = b - t

  // The aspect lock fits the smaller side to the larger one.
  if (aspect.value !== null) {
    const a = aspect.value
    // Decide which side to hold fixed, by whichever changed more.
    if (Math.abs(nw - s.cw) >= Math.abs(nh - s.ch)) {
      nh = nw / a
    } else {
      nw = nh * a
    }
    // Move the anchor: the opposite corner is the one held in place.
    if (dragMode.value === 'nw') { l = r - nw; t = b - nh }
    if (dragMode.value === 'ne') { t = b - nh }
    if (dragMode.value === 'sw') { l = r - nw }
    // For 'se' the left and top stay put while the width and height change.
    // Clamp so as not to run past the edges.
    if (l < 0) { nw += l; l = 0; nh = nw / a; t = (dragMode.value === 'nw' || dragMode.value === 'ne') ? b - nh : t }
    if (t < 0) { nh += t; t = 0; nw = nh * a; l = (dragMode.value === 'nw' || dragMode.value === 'sw') ? r - nw : l }
    if (l + nw > ds.w) { nw = ds.w - l; nh = nw / a }
    if (t + nh > ds.h) { nh = ds.h - t; nw = nh * a }
  }

  crop.value = { x: l, y: t, w: nw, h: nh }
}

function endDrag(): void {
  dragMode.value = null
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', endDrag)
}

async function applyCrop(): Promise<void> {
  if (!sourceImage.value) return
  const src = sourceImage.value
  const s = 1 / displayScale.value
  const sx = crop.value.x * s
  const sy = crop.value.y * s
  const sw = crop.value.w * s
  const sh = crop.value.h * s

  const outW = props.outputWidth ?? Math.round(sw)
  const outH = props.outputHeight ?? Math.round(sh)

  const canvas = document.createElement('canvas')
  canvas.width = outW
  canvas.height = outH
  const ctx = canvas.getContext('2d')
  if (!ctx) { adminToast.error(tr('Canvas недоступен.')); return }
  ctx.drawImage(src, sx, sy, sw, sh, 0, 0, outW, outH)

  // Saved as PNG by default, which is lossless; JPEG and WEBP follow the mime
  // type. In v1 it is png plus a quality setting, for transparency.
  const blob = await new Promise<Blob | null>((resolve) =>
    canvas.toBlob(resolve, 'image/png', props.quality ?? 0.92),
  )
  if (!blob) { adminToast.error(tr('Не удалось получить blob.')); return }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', blob, 'crop.png')
    const res = await getAdminClient().post<UploadedFile>('/uploads/image', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.setField(props.name, res)
    cancelCrop()
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] upload failed:', err)
    adminToast.error(tr('Не удалось загрузить.'))
  } finally {
    uploading.value = false
  }
}

function cancelCrop(): void {
  cleanupSource()
  sourceImage.value = null
}

function cleanupSource(): void {
  if (sourceUrl.value) {
    URL.revokeObjectURL(sourceUrl.value)
    sourceUrl.value = null
  }
}

function replace(): void {
  form.setField(props.name, null)
  void nextTick(pickFile)
}

function remove(): void {
  form.setField(props.name, null)
}

onBeforeUnmount(cleanupSource)
</script>

<template>
  <div class="admin-image-cropper">
    <label v-if="label" class="admin-image-cropper__label">
      {{ label }}<span v-if="required" class="admin-image-cropper__required">*</span>
    </label>

    <!-- empty -->
    <div v-if="!value && !sourceImage">
      <div
        class="admin-image-cropper__drop"
        :class="{ 'admin-image-cropper__drop--over': dragOver }"
        @click="pickFile"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop="onDrop"
      >
        <UidIcon :icon="ImageIcon" :size="28" />
        <p class="admin-image-cropper__hint">{{ tr('Кликните или перетащите картинку') }}</p>
        <p v-if="outputWidth && outputHeight" class="admin-image-cropper__hint-sub">
          {{ tRaw('Будет обрезано до :w×:h px', { w: outputWidth, h: outputHeight }) }}
        </p>
      </div>
      <input
        ref="fileInput"
        type="file"
        :accept="accept ?? 'image/*'"
        class="admin-image-cropper__input"
        @change="onFile"
      />
    </div>

    <!-- picked: crop UI -->
    <div v-else-if="sourceImage" class="admin-image-cropper__crop">
      <div
        class="admin-image-cropper__canvas-wrap"
        :style="{ width: displaySize.w + 'px', height: displaySize.h + 'px' }"
      >
        <img
          :src="sourceUrl ?? undefined"
          :width="displaySize.w"
          :height="displaySize.h"
          class="admin-image-cropper__img"
          alt=""
        />
        <div
          class="admin-image-cropper__rect"
          :style="{ left: crop.x + 'px', top: crop.y + 'px', width: crop.w + 'px', height: crop.h + 'px' }"
          @mousedown="startDrag('move', $event)"
        >
          <div class="admin-image-cropper__handle admin-image-cropper__handle--nw" @mousedown.stop="startDrag('nw', $event)"></div>
          <div class="admin-image-cropper__handle admin-image-cropper__handle--ne" @mousedown.stop="startDrag('ne', $event)"></div>
          <div class="admin-image-cropper__handle admin-image-cropper__handle--sw" @mousedown.stop="startDrag('sw', $event)"></div>
          <div class="admin-image-cropper__handle admin-image-cropper__handle--se" @mousedown.stop="startDrag('se', $event)"></div>
        </div>
      </div>
      <div class="admin-image-cropper__actions">
        <UidButton variant="primary" size="sm" :disabled="uploading" @click="applyCrop">
          <UidIcon :icon="Check" /> {{ uploading ? tr('Загрузка…') : tr('Применить') }}
        </UidButton>
        <UidButton variant="ghost" size="sm" :disabled="uploading" @click="cancelCrop">
          <UidIcon :icon="X" /> {{ tr('Отмена') }}
        </UidButton>
      </div>
    </div>

    <!-- uploaded -->
    <div v-else-if="value" class="admin-image-cropper__preview">
      <img :src="value.url" :alt="value.name" class="admin-image-cropper__preview-img" />
      <div class="admin-image-cropper__preview-info">
        <div class="admin-image-cropper__preview-name">{{ value.name }}</div>
        <div class="admin-image-cropper__preview-meta">{{ value.mime }} · {{ (value.size / 1024).toFixed(1) }} KB</div>
        <div class="admin-image-cropper__preview-actions">
          <UidButton variant="ghost" size="sm" @click="replace">
            <UidIcon :icon="Replace" /> {{ tr('Заменить') }}
          </UidButton>
          <UidButton variant="ghost" size="sm" @click="remove">
            <UidIcon :icon="X" /> {{ tr('Удалить') }}
          </UidButton>
        </div>
      </div>
    </div>

    <p v-if="help && !errorMsg" class="admin-image-cropper__help">{{ help }}</p>
    <p v-if="errorMsg" class="admin-image-cropper__error">{{ errorMsg }}</p>
  </div>
</template>

<style scoped>
.admin-image-cropper {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.admin-image-cropper__label {
  font-size: 13px;
  font-weight: 500;
}
.admin-image-cropper__required {
  color: var(--uid-color-danger, #dc2626);
  margin-left: 2px;
}
.admin-image-cropper__drop {
  border: 2px dashed var(--uid-color-border, #d1d5db);
  border-radius: 8px;
  padding: 32px 16px;
  text-align: center;
  /* Flex, not just `text-align: center`. The icon renders as a block-level
     svg, and text-align does not touch a block: the label sat centred while
     the icon stayed pinned to the left edge, 449px away from it on a wide
     field. Centring the box itself puts both on the same axis. */
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 120ms ease, border-color 120ms ease;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-image-cropper__drop:hover,
.admin-image-cropper__drop--over {
  border-color: var(--uid-color-primary, #2dd4bf);
  background: var(--uid-color-surface-2, #f3f4f6);
}
.admin-image-cropper__hint {
  margin: 8px 0 0;
  font-size: 13px;
}
.admin-image-cropper__hint-sub {
  margin: 4px 0 0;
  font-size: 12px;
  opacity: 0.7;
}
.admin-image-cropper__input {
  display: none;
}

.admin-image-cropper__crop {
  display: flex;
  flex-direction: column;
  gap: 12px;
  user-select: none;
}
.admin-image-cropper__canvas-wrap {
  position: relative;
  background: #f3f4f6;
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: 8px;
  overflow: hidden;
}
.admin-image-cropper__img {
  display: block;
  pointer-events: none;
}
.admin-image-cropper__rect {
  position: absolute;
  border: 2px solid var(--uid-color-primary, #2dd4bf);
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
  cursor: move;
  box-sizing: border-box;
}
.admin-image-cropper__handle {
  position: absolute;
  width: 12px;
  height: 12px;
  background: var(--uid-color-primary, #2dd4bf);
  border: 2px solid white;
  border-radius: 2px;
}
.admin-image-cropper__handle--nw { top: -7px; left: -7px; cursor: nwse-resize; }
.admin-image-cropper__handle--ne { top: -7px; right: -7px; cursor: nesw-resize; }
.admin-image-cropper__handle--sw { bottom: -7px; left: -7px; cursor: nesw-resize; }
.admin-image-cropper__handle--se { bottom: -7px; right: -7px; cursor: nwse-resize; }
.admin-image-cropper__actions {
  display: flex;
  gap: 8px;
}

.admin-image-cropper__preview {
  display: flex;
  gap: 16px;
  padding: 12px;
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: 8px;
  align-items: center;
}
.admin-image-cropper__preview-img {
  max-width: 160px;
  max-height: 100px;
  border-radius: 4px;
  background: repeating-conic-gradient(#eee 0% 25%, transparent 25% 50%) 0 0/16px 16px;
}
.admin-image-cropper__preview-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}
.admin-image-cropper__preview-name {
  font-size: 13px;
  font-weight: 500;
}
.admin-image-cropper__preview-meta {
  font-size: 12px;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-image-cropper__preview-actions {
  display: flex;
  gap: 8px;
  margin-top: 6px;
}

.admin-image-cropper__help {
  font-size: 12px;
  color: var(--uid-color-text-secondary, #62686f);
  margin: 0;
}
.admin-image-cropper__error {
  font-size: 12px;
  color: var(--uid-color-danger, #dc2626);
  margin: 0;
}
</style>

<script setup lang="ts">
/**
 * WysiwygField — the field registry's wrapper around `@dskripchenko/wysiwyg`.
 *
 * It is laravel-admin's default fallback for type='wysiwyg'. The editor is the
 * package's own, with no dependencies — no Tiptap, ProseMirror or Quill peer
 * dependencies — and the theme reaches it from the admin styles through the
 * `--dsk-wysiwyg-*` CSS variables.
 *
 * The image and link toolbar buttons emit events, since DskWysiwyg delegates
 * the UX operations to its host by design. Here:
 *   image-request → a file picker, an upload through `/uploads/image`, then
 *                   controller.chain().setImage(url), inserting at the caret.
 *   link-request  → a prompt() for the URL, then controller.chain().setLink(url).
 *
 * Image interactivity:
 *   - clicking an img selects it: an outline plus an overlay with corner handles.
 *   - dragging a corner handle resizes it, aspect-locked unless Shift is held.
 *   - dragstart on a selected img reorders it across the block nodes by drag
 *     and drop, with a drop-line indicator.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { DskWysiwyg, type EditorController } from '@dskripchenko/wysiwyg'
import '@dskripchenko/wysiwyg/style.css'
import { useFormState } from '../render/formState'
import { getAdminClient } from '../../stores/registry'
import { adminToast } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  /** The editor area's minimum height. */
  minHeight?: string
  /** The maximum height; past it the area overflows. */
  maxHeight?: string
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  minHeight: '200px',
  maxHeight: undefined,
})

const form = useFormState()

const value = computed<string>(() => (form.getField(props.name) as string | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(html: string): void {
  form.setField(props.name, html)
}

interface UploadResponse {
  url: string
  name: string
  mime: string
}

const controller = ref<EditorController | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const containerRef = ref<HTMLElement | null>(null)

function onReady(c: EditorController): void {
  controller.value = c
  attachImageInteractions(c.host)
}

function onImageRequest(): void {
  fileInput.value?.click()
}

async function onFilePicked(e: Event): Promise<void> {
  const file = (e.target as HTMLInputElement).files?.[0]
  ;(e.target as HTMLInputElement).value = ''
  if (!file || !controller.value) return
  try {
    const fd = new FormData()
    fd.append('file', file)
    const res = await getAdminClient().post<UploadResponse>('/uploads/image', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    controller.value.chain().setImage(res.url, res.name).run()
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] image upload failed:', err)
    adminToast.error(tr('Не удалось загрузить изображение.'))
  }
}

function onLinkRequest(currentUrl: string | null): void {
  if (!controller.value) return
  const next = window.prompt(tr('URL ссылки (пусто — удалить ссылку):'), currentUrl ?? '')
  if (next === null) return
  controller.value.chain().setLink(next.trim() === '' ? null : next.trim()).run()
}

/* ───────────────────── Image interactivity ───────────────────── */

interface Rect { x: number; y: number; w: number; h: number }

const selectedImg = ref<HTMLImageElement | null>(null)
const overlayRect = ref<Rect | null>(null)
const dropLine = ref<{ x: number; y: number; w: number } | null>(null)
let host: HTMLElement | null = null
let cleanupFns: Array<() => void> = []

function dispatchInputOnHost(): void {
  // Signal the DskWysiwyg engine: it listens for 'input' on the host, saves
  // the change and adds a history snapshot.
  host?.dispatchEvent(new Event('input', { bubbles: true }))
}

function refreshOverlayRect(): void {
  if (!selectedImg.value || !containerRef.value) {
    overlayRect.value = null
    return
  }
  const imgRect = selectedImg.value.getBoundingClientRect()
  const ctrRect = containerRef.value.getBoundingClientRect()
  overlayRect.value = {
    x: imgRect.left - ctrRect.left + containerRef.value.scrollLeft,
    y: imgRect.top - ctrRect.top + containerRef.value.scrollTop,
    w: imgRect.width,
    h: imgRect.height,
  }
}

function selectImg(img: HTMLImageElement): void {
  if (selectedImg.value === img) return
  if (selectedImg.value) selectedImg.value.classList.remove('admin-wysiwyg-img--selected')
  selectedImg.value = img
  img.classList.add('admin-wysiwyg-img--selected')
  img.setAttribute('draggable', 'true')
  refreshOverlayRect()
}

function clearSelection(): void {
  if (selectedImg.value) {
    selectedImg.value.classList.remove('admin-wysiwyg-img--selected')
    selectedImg.value.removeAttribute('draggable')
  }
  selectedImg.value = null
  overlayRect.value = null
}

function attachImageInteractions(h: HTMLElement): void {
  host = h

  const onHostClick = (e: MouseEvent): void => {
    const t = e.target as HTMLElement | null
    const img = t?.closest('img') as HTMLImageElement | null
    if (img && host?.contains(img)) selectImg(img)
    else clearSelection()
  }
  host.addEventListener('click', onHostClick)
  cleanupFns.push(() => host?.removeEventListener('click', onHostClick))

  const onDocClick = (e: MouseEvent): void => {
    // A click outside the container clears the selection.
    const t = e.target as HTMLElement | null
    if (containerRef.value && !containerRef.value.contains(t)) clearSelection()
  }
  document.addEventListener('click', onDocClick)
  cleanupFns.push(() => document.removeEventListener('click', onDocClick))

  const onScrollResize = (): void => refreshOverlayRect()
  window.addEventListener('scroll', onScrollResize, true)
  window.addEventListener('resize', onScrollResize)
  cleanupFns.push(() => window.removeEventListener('scroll', onScrollResize, true))
  cleanupFns.push(() => window.removeEventListener('resize', onScrollResize))

  // Drag-and-drop reorder.
  const onDragStart = (e: DragEvent): void => {
    const t = e.target as HTMLElement | null
    if (!t || !host?.contains(t) || t.tagName !== 'IMG') return
    const img = t as HTMLImageElement
    selectImg(img)
    e.dataTransfer?.setData('text/plain', 'img')
    if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
  }
  host.addEventListener('dragstart', onDragStart)
  cleanupFns.push(() => host?.removeEventListener('dragstart', onDragStart))

  const onDragOver = (e: DragEvent): void => {
    if (!selectedImg.value) return
    e.preventDefault()
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
    const target = blockUnderPoint(e.clientX, e.clientY)
    if (target && containerRef.value) {
      const blockRect = target.getBoundingClientRect()
      const ctrRect = containerRef.value.getBoundingClientRect()
      const above = e.clientY < blockRect.top + blockRect.height / 2
      const lineY = (above ? blockRect.top : blockRect.bottom) - ctrRect.top + containerRef.value.scrollTop
      dropLine.value = {
        x: blockRect.left - ctrRect.left + containerRef.value.scrollLeft,
        y: lineY,
        w: blockRect.width,
      }
    }
  }
  host.addEventListener('dragover', onDragOver)
  cleanupFns.push(() => host?.removeEventListener('dragover', onDragOver))

  const onDrop = (e: DragEvent): void => {
    if (!selectedImg.value || !host) return
    e.preventDefault()
    dropLine.value = null
    const target = blockUnderPoint(e.clientX, e.clientY)
    if (!target || target.contains(selectedImg.value)) {
      // A drop into the same block, or an invalid one: do nothing.
      return
    }
    const blockRect = target.getBoundingClientRect()
    const above = e.clientY < blockRect.top + blockRect.height / 2
    const img = selectedImg.value
    img.remove()
    if (above) target.parentNode?.insertBefore(img, target)
    else target.parentNode?.insertBefore(img, target.nextSibling)
    refreshOverlayRect()
    dispatchInputOnHost()
  }
  host.addEventListener('drop', onDrop)
  cleanupFns.push(() => host?.removeEventListener('drop', onDrop))

  const onDragEnd = (): void => { dropLine.value = null }
  host.addEventListener('dragend', onDragEnd)
  cleanupFns.push(() => host?.removeEventListener('dragend', onDragEnd))

  // A MutationObserver: when the img is removed or changed from outside, the
  // selection is dropped if it is no longer in the DOM.
  const mo = new MutationObserver(() => {
    if (selectedImg.value && !host?.contains(selectedImg.value)) clearSelection()
    else refreshOverlayRect()
  })
  mo.observe(host, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'style', 'width', 'height'] })
  cleanupFns.push(() => mo.disconnect())
}

function blockUnderPoint(x: number, y: number): HTMLElement | null {
  if (!host) return null
  // Hide the selected img for a moment, so that elementFromPoint does not return it.
  const prevPe = selectedImg.value?.style.pointerEvents ?? ''
  if (selectedImg.value) selectedImg.value.style.pointerEvents = 'none'
  const el = document.elementFromPoint(x, y) as HTMLElement | null
  if (selectedImg.value) selectedImg.value.style.pointerEvents = prevPe
  if (!el || !host.contains(el)) return null
  // Walk up to the host's nearest block child.
  let cur: HTMLElement | null = el
  while (cur && cur.parentElement && cur.parentElement !== host) cur = cur.parentElement
  return cur && cur.parentElement === host ? cur : null
}

/* ─── Resize handles ─── */
type Handle = 'nw' | 'ne' | 'sw' | 'se'
const resizing = ref<Handle | null>(null)
let resizeStart: { mx: number; my: number; w: number; h: number; aspect: number } | null = null

function startResize(handle: Handle, e: MouseEvent): void {
  if (!selectedImg.value) return
  e.preventDefault()
  e.stopPropagation()
  resizing.value = handle
  const img = selectedImg.value
  const rect = img.getBoundingClientRect()
  resizeStart = {
    mx: e.clientX,
    my: e.clientY,
    w: rect.width,
    h: rect.height,
    aspect: rect.width / Math.max(1, rect.height),
  }
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', endResize)
}

function onResizeMove(e: MouseEvent): void {
  if (!resizing.value || !resizeStart || !selectedImg.value) return
  const dx = e.clientX - resizeStart.mx
  const dy = e.clientY - resizeStart.my
  // The sign of the delta depends on the corner: nw/sw invert X, nw/ne invert Y.
  const sx = (resizing.value === 'nw' || resizing.value === 'sw') ? -1 : 1
  const sy = (resizing.value === 'nw' || resizing.value === 'ne') ? -1 : 1
  let newW = Math.max(24, resizeStart.w + dx * sx)
  let newH = Math.max(24, resizeStart.h + dy * sy)
  // The aspect ratio is locked by default; Shift frees it.
  if (!e.shiftKey) {
    // Follow whichever side changed more.
    if (Math.abs(dx * sx) >= Math.abs(dy * sy)) newH = newW / resizeStart.aspect
    else newW = newH * resizeStart.aspect
  }
  selectedImg.value.style.width = Math.round(newW) + 'px'
  selectedImg.value.style.height = Math.round(newH) + 'px'
  refreshOverlayRect()
}

function endResize(): void {
  if (resizing.value) {
    resizing.value = null
    resizeStart = null
    window.removeEventListener('mousemove', onResizeMove)
    window.removeEventListener('mouseup', endResize)
    dispatchInputOnHost()
  }
}

function handleStyle(h: Handle, r: Rect): Record<string, string> {
  const HANDLE = 10
  const half = HANDLE / 2
  let left = r.x
  let top = r.y
  if (h === 'nw') { left = r.x - half; top = r.y - half }
  if (h === 'ne') { left = r.x + r.w - half; top = r.y - half }
  if (h === 'sw') { left = r.x - half; top = r.y + r.h - half }
  if (h === 'se') { left = r.x + r.w - half; top = r.y + r.h - half }
  return { left: left + 'px', top: top + 'px', width: HANDLE + 'px', height: HANDLE + 'px' }
}

onBeforeUnmount(() => {
  cleanupFns.forEach(fn => fn())
  cleanupFns = []
  clearSelection()
})

// When the value changes from outside — a form reset, an undo — sync it in.
watch(value, () => {
  if (selectedImg.value && host && !host.contains(selectedImg.value)) clearSelection()
})
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div ref="containerRef" class="admin-field__control admin-wysiwyg-wrap">
      <DskWysiwyg
        :model-value="value"
        :placeholder="placeholder ?? undefined"
        :readonly="disabled"
        :min-height="minHeight"
        :max-height="maxHeight"
        @ready="onReady"
        @image-request="onImageRequest"
        @link-request="onLinkRequest"
        @update:model-value="onUpdate"
      />
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="admin-wysiwyg__file-input"
        @change="onFilePicked"
      />
      <!-- Resize handles overlay -->
      <template v-if="overlayRect">
        <div
          v-for="h in (['nw','ne','sw','se'] as const)"
          :key="h"
          class="admin-wysiwyg-img__handle"
          :class="`admin-wysiwyg-img__handle--${h}`"
          :style="handleStyle(h, overlayRect)"
          @mousedown="startResize(h, $event)"
        />
      </template>
      <!-- Drop-line indicator -->
      <div
        v-if="dropLine"
        class="admin-wysiwyg-img__dropline"
        :style="{
          left: dropLine.x + 'px',
          top: dropLine.y + 'px',
          width: dropLine.w + 'px',
        }"
      />
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>

<style scoped>
.admin-wysiwyg-wrap {
  position: relative;
}
.admin-wysiwyg__file-input {
  display: none;
}
.admin-wysiwyg-img__handle {
  position: absolute;
  background: var(--uid-color-primary, #2dd4bf);
  border: 2px solid #fff;
  border-radius: 2px;
  cursor: nwse-resize;
  z-index: 20;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.admin-wysiwyg-img__handle--ne,
.admin-wysiwyg-img__handle--sw {
  cursor: nesw-resize;
}
.admin-wysiwyg-img__dropline {
  position: absolute;
  height: 2px;
  background: var(--uid-color-primary, #2dd4bf);
  pointer-events: none;
  z-index: 19;
  box-shadow: 0 0 0 1px rgba(45, 212, 191, 0.4);
}
</style>

<style>
.admin-wysiwyg-img--selected {
  outline: 2px solid var(--uid-color-primary, #2dd4bf);
  outline-offset: 1px;
  cursor: move;
}
</style>

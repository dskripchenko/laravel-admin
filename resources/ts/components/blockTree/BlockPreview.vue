<script setup lang="ts">
/**
 * The preview pane of the block tree editor: the page for the current tree
 * in an iframe, reloaded a while after each change with the scroll kept; a
 * width switch; a click on a block selects its node, a selected node is
 * outlined and scrolled to. See preview.ts for the protocol.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { UidButton, UidIcon } from '@dskripchenko/ui'
import { Monitor, RefreshCw, Smartphone, Tablet } from 'lucide-vue-next'
import { trSafe as tr } from '../../stores/i18n'
import { apiErrorMessage } from '../../api/errors'
import {
  ensurePreviewStyle,
  fetchPreview,
  highlight,
  HIGHLIGHT_MESSAGE,
  previewTarget,
  refFromMessage,
  withBase,
} from './preview'
import { cloneNodes, stableJson, type BlockNode, type BlockRef } from './tree'

const props = withDefaults(defineProps<{
  url: string | null
  params?: Record<string, unknown> | null
  nodes: BlockNode[]
  selectedRef: BlockRef | null
  markerAttribute?: string
  delay?: number
}>(), {
  params: null,
  markerAttribute: 'data-builder-ref',
  delay: 700,
})

const emit = defineEmits<{ select: [ref: string] }>()

type Device = 'desktop' | 'tablet' | 'phone'
const device = ref<Device>('desktop')
const widths: Record<Device, string> = { desktop: '100%', tablet: '768px', phone: '390px' }
const devices = computed(() => [
  { key: 'desktop' as const, icon: Monitor, label: tr('Компьютер') },
  { key: 'tablet' as const, icon: Tablet, label: tr('Планшет') },
  { key: 'phone' as const, icon: Smartphone, label: tr('Телефон') },
])

const frame = ref<HTMLIFrameElement | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
let controller: AbortController | null = null
let timer: ReturnType<typeof setTimeout> | null = null
let blobUrl: string | null = null
let staleBlob: string | null = null
let keepScroll: { x: number; y: number } | null = null

async function reload(): Promise<void> {
  if (timer !== null) clearTimeout(timer)
  timer = null
  if (!props.url) return
  controller?.abort()
  const own = new AbortController()
  controller = own
  loading.value = true
  try {
    const html = await fetchPreview(props.url, { ...(props.params ?? {}), nodes: cloneNodes(props.nodes) }, own.signal)
    if (controller !== own) return
    show(html)
    error.value = null
  } catch (err) {
    if (own.signal.aborted) return
    error.value = apiErrorMessage(err, tr('Не удалось загрузить предпросмотр'))
  } finally {
    if (controller === own) loading.value = false
  }
}

function show(html: string): void {
  const el = frame.value
  if (!el || !props.url) return
  try {
    const win = el.contentWindow
    if (win && win.location.href !== 'about:blank') keepScroll = { x: win.scrollX, y: win.scrollY }
  } catch {
    keepScroll = null
  }
  const page = withBase(html, new URL('/', previewTarget(props.url)).href)
  if (typeof URL.createObjectURL === 'function' && typeof Blob !== 'undefined') {
    staleBlob = blobUrl
    blobUrl = URL.createObjectURL(new Blob([page], { type: 'text/html' }))
    el.src = blobUrl
  } else {
    el.srcdoc = page
  }
}

function frameDocument(): Document | null {
  try {
    return frame.value?.contentDocument ?? null
  } catch {
    return null
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target as Element | null
  const marker = target?.closest?.(`[${props.markerAttribute}]`)
  if (!marker) return
  event.preventDefault()
  emit('select', marker.getAttribute(props.markerAttribute) ?? '')
}

function onHover(event: MouseEvent): void {
  const doc = frameDocument()
  if (!doc) return
  doc.querySelectorAll('[data-block-tree-hover]').forEach((el) => el.removeAttribute('data-block-tree-hover'))
  const marker = (event.target as Element | null)?.closest?.(`[${props.markerAttribute}]`)
  const box = marker && (marker.firstElementChild ?? marker)
  box?.setAttribute('data-block-tree-hover', '')
}

function onLoad(): void {
  if (staleBlob) {
    URL.revokeObjectURL(staleBlob)
    staleBlob = null
  }
  const doc = frameDocument()
  if (!doc) return
  ensurePreviewStyle(doc)
  // Capture: the page's own handlers — a link, a slider — do not get the click first.
  doc.addEventListener('click', onClick, true)
  doc.addEventListener('mouseover', onHover)
  const win = frame.value?.contentWindow
  if (keepScroll && win) win.scrollTo(keepScroll.x, keepScroll.y)
  keepScroll = null
  if (props.selectedRef !== null) highlight(doc, props.markerAttribute, props.selectedRef, false)
}

function onMessage(event: MessageEvent): void {
  const ref = refFromMessage(event, frame.value?.contentWindow)
  if (ref !== null) emit('select', ref)
}

function schedule(): void {
  if (timer !== null) clearTimeout(timer)
  timer = setTimeout(() => void reload(), Math.max(0, props.delay))
}

watch(() => stableJson(props.nodes), schedule)
watch(() => [props.url, stableJson(props.params ?? {})], () => void reload())

watch(
  () => props.selectedRef,
  (ref) => {
    const win = frame.value?.contentWindow
    try {
      win?.postMessage({ type: HIGHLIGHT_MESSAGE, ref: ref === null ? null : String(ref) }, window.location.origin)
    } catch {
      // A page of another origin (a misconfigured address) cannot be told.
    }
    const doc = frameDocument()
    if (doc) highlight(doc, props.markerAttribute, ref)
  },
)

onMounted(() => {
  window.addEventListener('message', onMessage)
  void reload()
})

onBeforeUnmount(() => {
  window.removeEventListener('message', onMessage)
  controller?.abort()
  if (timer !== null) clearTimeout(timer)
  for (const url of [blobUrl, staleBlob]) if (url && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(url)
})

defineExpose({ reload, frame })
</script>

<template>
  <div class="admin-block-tree-preview">
    <div class="admin-block-tree-preview__bar">
      <div class="admin-block-tree-preview__devices" role="group" :aria-label="tr('Ширина предпросмотра')">
        <UidButton
          v-for="d in devices"
          :key="d.key"
          size="sm"
          :variant="device === d.key ? 'primary' : 'ghost'"
          :title="d.label"
          :aria-label="d.label"
          :aria-pressed="device === d.key"
          :data-device="d.key"
          @click="device = d.key"
        >
          <UidIcon :icon="d.icon" :size="16" />
        </UidButton>
      </div>
      <span v-if="loading" class="admin-block-tree-preview__state">{{ tr('Обновление…') }}</span>
      <UidButton size="sm" variant="ghost" :title="tr('Обновить')" :aria-label="tr('Обновить')" :disabled="!url" @click="reload">
        <UidIcon :icon="RefreshCw" :size="16" />
      </UidButton>
    </div>
    <div class="admin-block-tree-preview__stage">
      <p v-if="!url" class="admin-block-tree-preview__empty">{{ tr('Предпросмотр не настроен') }}</p>
      <template v-else>
        <p v-if="error" class="admin-block-tree-preview__error" role="alert">{{ error }}</p>
        <iframe
          ref="frame"
          class="admin-block-tree-preview__frame"
          :style="{ width: widths[device] }"
          :title="tr('Предпросмотр')"
          @load="onLoad"
        />
      </template>
    </div>
  </div>
</template>

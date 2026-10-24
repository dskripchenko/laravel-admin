<script setup lang="ts">
/**
 * The Markdown layout — `Layout::markdown($text)`.
 *
 * Drawn by the kit's UidMarkdown: the source is escaped before any markup is
 * added, fenced code blocks are highlighted and copyable, ```mermaid blocks
 * become diagrams (see ../markdown/blockRenderers). Headings get anchor ids;
 * `->toc()` adds a table of contents built from them.
 *
 * Links: anchors scroll within the page (UidMarkdown), links inside the panel
 * navigate through the router without a reload, external ones open in a new
 * tab.
 */
import { useRouter } from 'vue-router'
import { UidCard, UidMarkdown } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'
import { useCalloutTitles } from '../markdown/calloutTitles'

defineOptions({ inheritAttrs: false })

interface Props {
  markdown?: string | null
  toc?: boolean
  tocDepth?: number
  tocLabel?: string | null
  linkBase?: string | null
  stripMdExtension?: boolean
  imageBase?: string | null
  card?: boolean
}

withDefaults(defineProps<Props>(), {
  markdown: '',
  toc: false,
  tocDepth: 3,
  tocLabel: null,
  linkBase: null,
  stripMdExtension: true,
  imageBase: null,
  card: false,
})

const calloutTitles = useCalloutTitles()

// The router is optional: the layout also renders outside of the panel's app (in tests, in a host page).
let router: ReturnType<typeof useRouter> | undefined
try {
  router = useRouter()
} catch {
  router = undefined
}

/** Links within the panel go through the router; anchors are UidMarkdown's. */
function onContentClick(event: MouseEvent): void {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return
  }
  const anchor = (event.target as HTMLElement | null)?.closest?.('a')
  if (!anchor || anchor.target === '_blank') return
  const href = anchor.getAttribute('href') ?? ''
  if (href.startsWith('#') || !router || typeof window === 'undefined') return
  let url: URL
  try {
    url = new URL(anchor.href, window.location.href)
  } catch {
    return
  }
  if (url.origin !== window.location.origin) return
  const base = (router.options.history.base ?? '').replace(/\/$/, '')
  if (base !== '' && !(url.pathname === base || url.pathname.startsWith(`${base}/`))) return
  event.preventDefault()
  void router.push(url.pathname.slice(base.length) + url.search + url.hash)
}
</script>

<template>
  <component
    :is="card ? UidCard : 'div'"
    v-bind="card ? { padding: 'lg' } : {}"
    class="admin-markdown-layout"
  >
    <UidMarkdown
      class="admin-markdown-layout__body"
      :source="markdown ?? ''"
      :toc="toc"
      :toc-depth="tocDepth"
      :toc-label="tocLabel || tr('На этой странице')"
      :link-base="linkBase ?? undefined"
      :strip-md-extension="stripMdExtension"
      :image-base="imageBase ?? undefined"
      :callout-titles="calloutTitles"
      @click="onContentClick"
    />
  </component>
</template>

<style>
.admin-markdown-layout { min-width: 0; margin-bottom: var(--uid-space-md, 12px); }
/* The page host and the screen's card clip with overflow: hidden, which makes
   them scroll containers and pins a sticky element to them instead of to the
   scrolling page. `clip` clips the same without becoming one. */
.admin-page-host:has(.uid-markdown--toc),
.uid-card:has(.uid-markdown--toc) { overflow: clip; }
</style>

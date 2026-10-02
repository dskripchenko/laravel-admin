<script setup lang="ts">
/**
 * The Markdown layout — `Layout::markdown($text)`.
 *
 * The text goes through the built-in safe renderer (the source is escaped
 * before any markup is added), with the top-level fenced code blocks drawn by
 * UidCode so that they are highlighted and copyable. Headings get anchor ids;
 * `->toc()` adds a table of contents built from them.
 *
 * Links: anchors scroll within the page, links inside the panel navigate
 * through the router without a reload, external ones open in a new tab.
 */
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { UidCard, UidCode } from '@dskripchenko/ui'
import {
  createSlugger,
  renderMarkdown,
  splitMarkdown,
  type MarkdownHeading,
} from '../fields/support/markdown'
import { trSafe as tr } from '../../stores/i18n'

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

const props = withDefaults(defineProps<Props>(), {
  markdown: '',
  toc: false,
  tocDepth: 3,
  tocLabel: null,
  linkBase: null,
  stripMdExtension: true,
  imageBase: null,
  card: false,
})

/** The fence languages UidCode knows under another name. */
const LANGUAGE_ALIASES: Record<string, string> = {
  blade: 'html',
  console: 'bash',
  env: 'bash',
  dotenv: 'bash',
  terminal: 'bash',
  ps1: 'bash',
  jsonc: 'json',
  json5: 'json',
}

type Block =
  | { kind: 'html'; html: string }
  | { kind: 'code'; code: string; language: string }

const rendered = computed<{ blocks: Block[]; headings: MarkdownHeading[] }>(() => {
  const slugger = createSlugger()
  const headings: MarkdownHeading[] = []
  const blocks: Block[] = splitMarkdown(props.markdown ?? '').map((segment) => {
    if (segment.kind === 'code') {
      const language = LANGUAGE_ALIASES[segment.language] ?? segment.language
      return { kind: 'code', code: segment.code, language }
    }
    return {
      kind: 'html',
      html: renderMarkdown(segment.source, {
        slugger,
        onHeading: (heading) => headings.push(heading),
        linkBase: props.linkBase,
        stripMdExtension: props.stripMdExtension,
        imageBase: props.imageBase,
        internalLinksInPlace: true,
      }),
    }
  })
  return { blocks, headings }
})

const tocItems = computed<MarkdownHeading[]>(() =>
  props.toc
    ? rendered.value.headings.filter((h) => h.level >= 2 && h.level <= (props.tocDepth ?? 3))
    : [],
)

// The router is optional: the layout also renders outside of the panel's app (in tests, in a host page).
let router: ReturnType<typeof useRouter> | undefined
try {
  router = useRouter()
} catch {
  router = undefined
}

const root = ref<HTMLElement | null>(null)

function scrollToId(id: string, smooth = true): boolean {
  if (!id || !root.value) return false
  const target = root.value.querySelector(`[id="${CSS.escape(id)}"]`)
  if (!(target instanceof HTMLElement)) return false
  target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' })
  return true
}

function onTocClick(event: MouseEvent, id: string): void {
  event.preventDefault()
  if (scrollToId(id) && typeof history !== 'undefined') {
    history.replaceState(history.state, '', `#${encodeURIComponent(id)}`)
  }
}

/** Anchors scroll in place; links within the panel go through the router. */
function onContentClick(event: MouseEvent): void {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return
  }
  const anchor = (event.target as HTMLElement | null)?.closest?.('a')
  if (!anchor || anchor.target === '_blank') return
  const href = anchor.getAttribute('href') ?? ''
  if (href.startsWith('#')) {
    event.preventDefault()
    const id = decodeURIComponent(href.slice(1))
    if (scrollToId(id) && typeof history !== 'undefined') {
      history.replaceState(history.state, '', href)
    }
    return
  }
  if (!router || typeof window === 'undefined') return
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

function scrollToLocationHash(): void {
  if (typeof window === 'undefined' || !window.location.hash) return
  void nextTick(() => scrollToId(decodeURIComponent(window.location.hash.slice(1)), false))
}

onMounted(scrollToLocationHash)
watch(() => props.markdown, scrollToLocationHash)
</script>

<template>
  <component
    :is="card ? UidCard : 'div'"
    v-bind="card ? { padding: 'lg' } : {}"
    :class="['admin-markdown-layout', tocItems.length > 0 ? 'admin-markdown-layout--toc' : '']"
  >
    <div ref="root" class="admin-markdown-layout__grid">
      <!-- eslint-disable vue/no-v-html -- renderMarkdown escapes the source before adding markup -->
      <div class="admin-markdown admin-markdown-layout__body" @click="onContentClick">
        <template v-for="(block, idx) in rendered.blocks" :key="idx">
          <div v-if="block.kind === 'html'" class="admin-markdown-layout__html" v-html="block.html" />
          <UidCode
            v-else
            class="admin-markdown-layout__code"
            :code="block.code"
            :language="block.language || undefined"
            copy
          />
        </template>
      </div>
      <!-- eslint-enable vue/no-v-html -->
      <nav
        v-if="tocItems.length > 0"
        class="admin-markdown-layout__toc"
        :aria-label="tocLabel || tr('На этой странице')"
      >
        <div class="admin-markdown-layout__toc-title">{{ tocLabel || tr('На этой странице') }}</div>
        <ul>
          <li
            v-for="item in tocItems"
            :key="item.id"
            :class="`admin-markdown-layout__toc-item admin-markdown-layout__toc-item--l${item.level}`"
          >
            <a :href="`#${item.id}`" @click="onTocClick($event, item.id)">{{ item.text }}</a>
          </li>
        </ul>
      </nav>
    </div>
  </component>
</template>

<style>
.admin-markdown-layout { min-width: 0; margin-bottom: var(--uid-space-md, 12px); }
.admin-markdown-layout__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--uid-space-xl, 24px);
  align-items: start;
}
.admin-markdown-layout--toc .admin-markdown-layout__grid {
  grid-template-columns: minmax(0, 1fr) 220px;
}
.admin-markdown-layout__body {
  font-size: var(--uid-font-size-md, 15px);
  line-height: 1.65;
  min-width: 0;
}
.admin-markdown-layout__html > :first-child { margin-top: 0; }
.admin-markdown-layout__html > :last-child { margin-bottom: 0; }
.admin-markdown-layout__body > * + * { margin-top: var(--uid-space-md, 12px); }
.admin-markdown-layout__body h1,
.admin-markdown-layout__body h2,
.admin-markdown-layout__body h3 { scroll-margin-top: 72px; }
.admin-markdown-layout__body h2 {
  margin-top: var(--uid-space-xl, 24px);
  padding-bottom: var(--uid-space-xs, 6px);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-markdown-layout__code { width: 100%; }
.admin-markdown-layout__toc {
  position: sticky;
  top: var(--uid-space-lg, 16px);
  max-height: calc(100vh - 96px);
  overflow: auto;
  font-size: var(--uid-font-size-sm);
  border-left: 1px solid var(--uid-border-subtle);
  padding-left: var(--uid-space-md, 12px);
}
.admin-markdown-layout__toc-title {
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
  margin-bottom: var(--uid-space-xs, 6px);
}
.admin-markdown-layout__toc ul { list-style: none; margin: 0; padding: 0; }
.admin-markdown-layout__toc-item { margin: 2px 0; }
.admin-markdown-layout__toc-item--l3 { padding-left: 12px; }
.admin-markdown-layout__toc-item--l4,
.admin-markdown-layout__toc-item--l5,
.admin-markdown-layout__toc-item--l6 { padding-left: 24px; }
.admin-markdown-layout__toc a {
  color: var(--uid-text-secondary);
  text-decoration: none;
}
.admin-markdown-layout__toc a:hover { color: var(--uid-accent); }
/* The page host and the screen's card clip with overflow: hidden, which makes
   them scroll containers and pins a sticky element to them instead of to the
   scrolling page. `clip` clips the same without becoming one. */
.admin-page-host:has(.admin-markdown-layout--toc),
.uid-card:has(.admin-markdown-layout--toc) { overflow: clip; }
@media (max-width: 1100px) {
  .admin-markdown-layout--toc .admin-markdown-layout__grid { grid-template-columns: minmax(0, 1fr); }
  .admin-markdown-layout__toc {
    position: static;
    order: -1;
    max-height: none;
    border-left: 0;
    padding: var(--uid-space-sm, 8px) var(--uid-space-md, 12px);
    border-radius: var(--uid-radius-md);
    background: var(--uid-surface-sunken, var(--uid-surface-raised));
  }
}
</style>

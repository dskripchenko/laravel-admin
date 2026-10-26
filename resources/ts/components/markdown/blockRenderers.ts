/**
 * Fenced-block renderers for every markdown the panel shows — the Markdown
 * layout, field preview, widget and infolist entry, all drawn by the kit's
 * UidMarkdown.
 *
 * ```mermaid blocks become diagrams through @dskripchenko/diagrams. It is
 * imported on first use, so a page without diagrams never downloads it. The
 * SVG it returns escapes every label by construction, which is what makes it
 * safe to insert as markup; a source it cannot read comes back as an error,
 * and UidMarkdown then shows the code with the message.
 *
 * ```openapi blocks — a request described in YAML or JSON, or a whole OpenAPI
 * document — become live API widgets through @dskripchenko/openapi: the
 * operation with try-it, code samples, TypeScript types and PDF. In the
 * WYSIWYG editor the same widget is the block's editor, with a source tab and
 * a request constructor. Loaded on first use as well.
 *
 * ```flow blocks — a process model in JSON, or a Mermaid `stateDiagram-v2` /
 * `flowchart` — become a read-only process view of @dskripchenko/flow: pan,
 * zoom, sub-processes, the details of a step. Loaded on first use too.
 *
 * ```video and ```audio blocks — a bare URL, JSON or a little YAML (see
 * ../media/mediaBlock) — become the kit's players, UidVideo and UidAudio. The
 * players are part of the kit the panel already has; an HLS stream loads the
 * kit's adapter on first use.
 */
import { createVNode, defineComponent, h, render, type App, type AppContext, type Component } from 'vue'
import { UidAudio, UidVideo } from '@dskripchenko/ui'
import { MediaBlockError, parseMediaBlock } from '../media/mediaBlock'
import { cssSize, toAudioTracks, toVideoConfig } from '../media/mediaSource'
import { provideKitLocale } from '../../composables/useKitLocale'
import type { Graph } from '@dskripchenko/flow'
import { flowLocale, loadFlow, loadFlowMermaid } from '../flow/loadFlow'
import {
  markdownBlockRenderersKey,
  type UidMarkdownBlockRenderer,
  type UidMarkdownBlockRenderers,
} from '@dskripchenko/ui'

type DiagramsModule = typeof import('@dskripchenko/diagrams')
type OpenApiModule = typeof import('@dskripchenko/openapi/vue')

let diagrams: Promise<DiagramsModule> | null = null
/** Ids inside each SVG must not collide between diagrams on one page. */
let sequence = 0

/** @internal Replaces the dynamic import, for tests. */
export function setDiagramsLoader(loader: (() => Promise<DiagramsModule>) | null): void {
  diagrams = loader ? loader() : null
}

function loadDiagrams(): Promise<DiagramsModule> {
  diagrams ??= import('@dskripchenko/diagrams')
  return diagrams
}

export const mermaidRenderer: UidMarkdownBlockRenderer = async (code) => {
  const { render, DiagramError } = await loadDiagrams()
  try {
    return { html: render(code, { idPrefix: `admin-dg-${++sequence}` }).svg }
  } catch (error) {
    if (error instanceof DiagramError) {
      return { error: error.hint ? `${error.message}. ${error.hint}` : error.message }
    }
    throw error
  }
}

let openapi: Promise<OpenApiModule> | null = null
/** The panel app: its provides (the kit's theme, i18n) reach the widgets. */
let appContext: AppContext | null = null

/** @internal Replaces the dynamic import, for tests. */
export function setOpenApiLoader(loader: (() => Promise<OpenApiModule>) | null): void {
  openapi = loader ? loader() : null
}

function loadOpenApi(): Promise<OpenApiModule> {
  openapi ??= Promise.all([
    import('@dskripchenko/openapi/vue'),
    import('@dskripchenko/openapi/style.css'),
  ]).then(([module]) => module)
  return openapi
}

export const openapiRenderer: UidMarkdownBlockRenderer = async (code, ctx) => {
  const { openApiBlockRenderer } = await loadOpenApi()
  // The widget speaks the panel's language where it knows it (en, ru).
  const locale = document.documentElement.lang || undefined
  return openApiBlockRenderer({ appContext: appContext ?? undefined, locale })(code, ctx)
}

/** A ```flow block's source as a model: JSON, or Mermaid state/flowchart source. */
async function flowGraph(code: string): Promise<Graph> {
  const { core } = await loadFlow()
  const source = code.trim()
  if (source.startsWith('{')) return core.parseGraph(source)
  const { fromMermaid } = await loadFlowMermaid()
  return fromMermaid(source)
}

function flowError(error: unknown): string {
  return error instanceof Error ? error.message : String(error)
}

export const flowRenderer: UidMarkdownBlockRenderer = async (code) => {
  let graph: Graph
  try {
    graph = await flowGraph(code)
  } catch (error) {
    return { error: flowError(error) }
  }
  const { vue } = await loadFlow()
  return {
    mount(el) {
      el.classList.add('admin-md-flow')
      el.style.height = '420px'
      // In the panel's context: its provides (the kit's theme and toasts) reach the view.
      const handle = vue.mountFlow(el, {
        graph,
        appContext: appContext ?? undefined,
        locale: flowLocale(),
        minimap: false,
        statusBar: false,
        toastProvider: false,
        style: { height: '100%' },
      })
      return {
        update(next) {
          flowGraph(next).then((g) => handle.update({ graph: g }), () => undefined)
        },
        destroy() {
          handle.unmount()
        },
      }
    },
  }
}

type PlayerProps = Record<string, unknown>

function flag(v: unknown): boolean | undefined {
  return typeof v === 'boolean' ? v : undefined
}

function text(v: unknown): string | undefined {
  return typeof v === 'string' && v !== '' ? v : undefined
}

/** UidVideo's props from a ```video block. */
export function videoBlockProps(code: string): PlayerProps {
  const data = parseMediaBlock(code)
  const o = Array.isArray(data) ? {} : data
  const config = toVideoConfig(Array.isArray(data) ? data : ('sources' in o ? o : { ...o, sources: [o] }))
  if (config.sources.length === 0) throw new MediaBlockError('no video source: give `src` or `sources`')
  return {
    sources: config.sources,
    poster: config.poster,
    tracks: config.tracks,
    thumbnails: config.thumbnails,
    title: text(o.title),
    aspectRatio: typeof o.aspectRatio === 'number' ? String(o.aspectRatio) : text(o.aspectRatio)?.replace(':', ' / '),
    autoplay: flag(o.autoplay),
    muted: flag(o.muted) ?? flag(o.autoplay),
    loop: flag(o.loop),
    maxWidth: cssSize(typeof o.maxWidth === 'number' || typeof o.maxWidth === 'string' ? o.maxWidth : null),
  }
}

/** UidAudio's props from an ```audio block: one track or a playlist. */
export function audioBlockProps(code: string): PlayerProps {
  const data = parseMediaBlock(code)
  const o = Array.isArray(data) ? {} : data
  const tracks = toAudioTracks(Array.isArray(data) ? data : (o.tracks ?? o))
  if (tracks.length === 0) throw new MediaBlockError('no audio source: give `src` or `tracks`')
  const variant = o.mini === true || o.variant === 'mini' ? 'mini' : 'default'
  const peaks = Array.isArray(o.peaks) ? o.peaks.filter((p): p is number => typeof p === 'number') : undefined
  const common = { waveform: flag(o.waveform), loop: flag(o.loop), variant, maxWidth: cssSize(text(o.maxWidth) ?? (typeof o.maxWidth === 'number' ? o.maxWidth : null)) }
  if (tracks.length === 1) {
    const [t] = tracks
    return {
      ...common,
      src: t.src,
      type: t.type,
      title: text(o.title) ?? t.title,
      artist: text(o.artist) ?? t.artist,
      peaks: peaks ?? t.peaks,
    }
  }
  return { ...common, tracks }
}

/**
 * The player under the kit's locale. The block is mounted outside the shell's
 * tree, and the app context carries only the app-level provides: the locale
 * AdminApp provides would not reach it.
 */
const LocalizedPlayer = defineComponent({
  name: 'AdminMediaBlockPlayer',
  inheritAttrs: false,
  props: { is: { type: [Object, Function], required: true } },
  setup(props, { attrs }) {
    provideKitLocale()
    return () => h(props.is as Component, attrs)
  },
})

/**
 * A player mounted into a live block, in the panel's context (its Pinia, its
 * locale). A changed source re-renders it in place.
 */
function mountPlayer(
  el: HTMLElement,
  component: Component,
  kind: 'video' | 'audio',
  initial: PlayerProps,
  toProps: (code: string) => PlayerProps,
) {
  el.classList.add('admin-md-media', `admin-md-media--${kind}`)
  const draw = (p: PlayerProps): void => {
    const { maxWidth, ...rest } = p
    el.style.maxWidth = typeof maxWidth === 'string' ? maxWidth : ''
    // The key follows the sources, so a new address is a new player.
    const key = JSON.stringify(rest.sources ?? rest.tracks ?? rest.src)
    const vnode = createVNode(LocalizedPlayer, { ...rest, is: component, key })
    vnode.appContext = appContext
    render(vnode, el)
  }
  draw(initial)
  return {
    update(next: string) {
      try {
        draw(toProps(next))
      } catch {
        // A half-typed source keeps the last good player.
      }
    },
    destroy() {
      render(null, el)
    },
  }
}

function mediaRenderer(
  component: Component,
  kind: 'video' | 'audio',
  toProps: (code: string) => PlayerProps,
): UidMarkdownBlockRenderer {
  return (code) => {
    let initial: PlayerProps
    try {
      initial = toProps(code)
    } catch (error) {
      if (error instanceof MediaBlockError) return { error: error.message }
      throw error
    }
    return { mount: (el) => mountPlayer(el, component, kind, initial, toProps) }
  }
}

export const videoRenderer: UidMarkdownBlockRenderer = mediaRenderer(UidVideo, 'video', videoBlockProps)
export const audioRenderer: UidMarkdownBlockRenderer = mediaRenderer(UidAudio, 'audio', audioBlockProps)

/** The renderers the panel registers app-wide. */
export const builtinMarkdownRenderers: UidMarkdownBlockRenderers = Object.freeze({
  mermaid: mermaidRenderer,
  openapi: openapiRenderer,
  flow: flowRenderer,
  video: videoRenderer,
  audio: audioRenderer,
})

/**
 * Registers the built-in renderers for the whole app. A host adds its own
 * the same way in `onAppCreated`, spreading these first:
 * `app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`.
 */
export function provideBuiltinMarkdownRenderers(app: App): void {
  appContext = app._context
  app.provide(markdownBlockRenderersKey, builtinMarkdownRenderers)
}

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
 */
import type { App, AppContext } from 'vue'
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

/** The renderers the panel registers app-wide. */
export const builtinMarkdownRenderers: UidMarkdownBlockRenderers = Object.freeze({
  mermaid: mermaidRenderer,
  openapi: openapiRenderer,
  flow: flowRenderer,
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

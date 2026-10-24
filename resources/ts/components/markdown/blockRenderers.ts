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
 */
import type { App } from 'vue'
import {
  markdownBlockRenderersKey,
  type UidMarkdownBlockRenderer,
  type UidMarkdownBlockRenderers,
} from '@dskripchenko/ui'

type DiagramsModule = typeof import('@dskripchenko/diagrams')

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

/** The renderers the panel registers app-wide. */
export const builtinMarkdownRenderers: UidMarkdownBlockRenderers = Object.freeze({
  mermaid: mermaidRenderer,
})

/**
 * Registers the built-in renderers for the whole app. A host adds its own
 * the same way in `onAppCreated`, spreading these first:
 * `app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`.
 */
export function provideBuiltinMarkdownRenderers(app: App): void {
  app.provide(markdownBlockRenderersKey, builtinMarkdownRenderers)
}

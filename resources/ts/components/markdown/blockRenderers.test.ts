import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, h, inject } from 'vue'
import { markdownBlockRenderersKey } from '@dskripchenko/ui'
import * as diagrams from '@dskripchenko/diagrams'
import {
  builtinMarkdownRenderers,
  mermaidRenderer,
  provideBuiltinMarkdownRenderers,
  setDiagramsLoader,
} from './blockRenderers'

afterEach(() => setDiagramsLoader(null))

describe('mermaid block renderer', () => {
  it('turns a diagram into SVG markup', async () => {
    const result = await mermaidRenderer('flowchart LR\n  A --> B', { language: 'mermaid', index: 0 })
    expect(result.error).toBeUndefined()
    expect(result.html).toMatch(/^<svg[\s\S]*<\/svg>$/)
  })

  it('gives every diagram its own ids', async () => {
    const source = 'flowchart LR\n  A --> B'
    const a = await mermaidRenderer(source, { language: 'mermaid', index: 0 })
    const b = await mermaidRenderer(source, { language: 'mermaid', index: 1 })
    const id = (svg: string) => /<svg[^>]* id="([^"]+)"/.exec(svg)?.[1]
    expect(id(a.html!)).not.toBe(id(b.html!))
  })

  it('reports a source it cannot read as an error, with the position', async () => {
    const result = await mermaidRenderer('flowchart LR\n  A -> ', { language: 'mermaid', index: 0 })
    expect(result.html).toBeUndefined()
    expect(result.error).toMatch(/line 2/)
  })

  it('lets other failures through', async () => {
    setDiagramsLoader(async () => ({
      ...diagrams,
      render: () => {
        throw new TypeError('broken')
      },
    }))
    await expect(mermaidRenderer('pie\n "a" : 1', { language: 'mermaid', index: 0 })).rejects.toThrow('broken')
  })

  it('loads the diagrams package only when a diagram is rendered', async () => {
    const loader = vi.fn(async () => diagrams)
    setDiagramsLoader(null)
    // Nothing is loaded until the renderer runs; the loader stands in for import().
    expect(loader).not.toHaveBeenCalled()
    setDiagramsLoader(loader)
    await mermaidRenderer('pie\n "a" : 1', { language: 'mermaid', index: 0 })
    expect(loader).toHaveBeenCalledTimes(1)
    await mermaidRenderer('pie\n "b" : 1', { language: 'mermaid', index: 1 })
    expect(loader).toHaveBeenCalledTimes(1)
  })

  it('is registered app-wide under the kit key', () => {
    let seen: unknown
    const app = createApp(
      defineComponent({
        setup() {
          seen = inject(markdownBlockRenderersKey)
          return () => h('div')
        },
      }),
    )
    provideBuiltinMarkdownRenderers(app)
    app.mount(document.createElement('div'))
    expect(seen).toBe(builtinMarkdownRenderers)
    expect(Object.keys(builtinMarkdownRenderers)).toEqual(['mermaid'])
    app.unmount()
  })
})

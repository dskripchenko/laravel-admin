import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, h, inject } from 'vue'
import { markdownBlockRenderersKey } from '@dskripchenko/ui'
import * as diagrams from '@dskripchenko/diagrams'
import {
  builtinMarkdownRenderers,
  mermaidRenderer,
  openapiRenderer,
  provideBuiltinMarkdownRenderers,
  setDiagramsLoader,
  setOpenApiLoader,
} from './blockRenderers'

afterEach(() => {
  setDiagramsLoader(null)
  setOpenApiLoader(null)
})

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
    expect(Object.keys(builtinMarkdownRenderers)).toEqual(['mermaid', 'openapi', 'flow'])
    app.unmount()
  })
})

describe('openapi block renderer', () => {
  const BLOCK = 'method: GET\nurl: https://api.example.com/v1/items/{id}\nsummary: Get an item'

  it('answers with a live widget, read-only or editable as the host says', async () => {
    const result = await openapiRenderer(BLOCK, { language: 'openapi', index: 0 })
    expect(result.html).toBeUndefined()
    expect(typeof result.mount).toBe('function')

    const el = document.createElement('div')
    document.body.appendChild(el)
    const changes: string[] = []
    const handle = result.mount!(el, { code: BLOCK, language: 'openapi', editable: true, setCode: (c) => changes.push(c) })
    await new Promise((r) => setTimeout(r, 0))
    expect(el.textContent).toContain('Get an item')
    expect(el.textContent).toContain('/v1/items/{id}')
    if (handle && typeof handle === 'object') handle.destroy?.()
    else if (typeof handle === 'function') handle()
    expect(el.textContent).toBe('')
    el.remove()
  })

  it('loads the openapi package only when a block is rendered', async () => {
    const real = await import('@dskripchenko/openapi/vue')
    const loader = vi.fn(async () => real)
    setOpenApiLoader(loader)
    await openapiRenderer(BLOCK, { language: 'openapi', index: 0 })
    await openapiRenderer(BLOCK, { language: 'openapi', index: 1 })
    expect(loader).toHaveBeenCalledTimes(1)
  })
})


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
    expect(Object.keys(builtinMarkdownRenderers)).toEqual([
      'mermaid', 'plantuml', 'puml', 'dot', 'graphviz', 'd2', 'dbml', 'structurizr', 'wardley',
      'bpmn', 'dmn', 'drawio', 'openapi', 'asyncapi', 'flow', 'kanban', 'calendar', 'video', 'audio',
    ])
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

describe('blocks in the other notations', () => {
  const render = async (language: string, code: string) => builtinMarkdownRenderers[language]!(code, { language, index: 0 })

  it('draws every diagram syntax by its own language', async () => {
    expect((await render('dot', 'digraph { a -> b }')).html).toContain('<svg')
    expect((await render('graphviz', 'digraph { a -> b }')).html).toContain('<svg')
    expect((await render('plantuml', '@startuml\nAlice -> Bob: hi\n@enduml')).html).toContain('<svg')
    expect((await render('d2', 'a -> b')).html).toContain('<svg')
    expect((await render('dbml', 'Table users {\n  id int [pk]\n}')).html).toContain('<svg')
    expect((await render('wardley', 'component A [0.5, 0.5]')).html).toContain('<svg')
    expect((await render('dot', 'digraph {')).error).toBeTruthy()
  })

  it('draws BPMN and DMN models, a DMN block with its decision tables', async () => {
    const bpmnXml = `<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" id="d"><process id="p"><startEvent id="s"/><task id="t" name="Check"/></process><bpmndi:BPMNDiagram id="dg"><bpmndi:BPMNPlane id="pl" bpmnElement="p"><bpmndi:BPMNShape id="s_di" bpmnElement="s"><dc:Bounds x="10" y="10" width="36" height="36"/></bpmndi:BPMNShape><bpmndi:BPMNShape id="t_di" bpmnElement="t"><dc:Bounds x="100" y="0" width="100" height="80"/></bpmndi:BPMNShape></bpmndi:BPMNPlane></bpmndi:BPMNDiagram></definitions>`
    expect((await render('bpmn', bpmnXml)).html).toContain('<svg')
    const dmnXml = `<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/" id="d" name="d" namespace="x"><inputData id="i" name="Amount"/><decision id="dec" name="Discount"><informationRequirement id="r"><requiredInput href="#i"/></informationRequirement><decisionTable id="t"><input id="in" label="Amount"><inputExpression id="e" typeRef="number"><text>amount</text></inputExpression></input><output id="o" name="discount" typeRef="number"/><rule id="r1"><inputEntry id="ie"><text>&gt; 100</text></inputEntry><outputEntry id="oe"><text>10</text></outputEntry></rule></decisionTable></decision></definitions>`
    const dmn = await render('dmn', dmnXml)
    expect(dmn.html).toContain('<svg')
    expect(dmn.html).toContain('dmn-decision')
    expect((await render('bpmn', '<nope/>')).error).toBeTruthy()
  })

  it('draws a draw.io document', async () => {
    const xml = '<mxfile><diagram id="d" name="p"><mxGraphModel><root><mxCell id="0"/><mxCell id="1" parent="0"/><mxCell id="2" value="Box" vertex="1" parent="1"><mxGeometry x="10" y="10" width="80" height="40" as="geometry"/></mxCell></root></mxGraphModel></diagram></mxfile>'
    expect((await render('drawio', xml)).html).toContain('<svg')
  })

  it('shows a kanban and a calendar read-only, and edits them in place in the editor', async () => {
    const kanban = await render('kanban', '## To do\n- Write the docs\n\n## Done\n- Design')
    expect(kanban.error).toBeUndefined()
    const view = document.createElement('div')
    const handle = kanban.mount!(view, { code: '', language: 'kanban', editable: false, setCode: () => {} }) as { destroy(): void }
    expect(view.querySelectorAll('.uid-kanban__card')).toHaveLength(2)
    expect(view.querySelector('.uid-kanban--editable')).toBeNull()
    handle.destroy()

    const edited = document.createElement('div')
    const setCode = vi.fn()
    kanban.mount!(edited, { code: '', language: 'kanban', editable: true, setCode })
    expect(edited.querySelector('.uid-kanban--editable')).not.toBeNull()

    expect((await render('kanban', 'just text')).error).toMatch(/No columns/)

    const calendar = await render('calendar', '2026-11-03 10:00-11:00 Release demo\nevery mon 11:00 Planning')
    expect(calendar.error).toBeUndefined()
    const cal = document.createElement('div')
    calendar.mount!(cal, { code: '', language: 'calendar', editable: false, setCode: () => {} })
    expect(cal.querySelector('.uid-event-calendar')).not.toBeNull()
  })
})


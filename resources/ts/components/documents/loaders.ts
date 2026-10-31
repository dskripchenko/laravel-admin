/**
 * The document packages, loaded on first use: the diagrams, BPMN/DMN,
 * draw.io and the AsyncAPI reference. The fenced blocks of markdown
 * (../markdown/blockRenderers) and the screen layouts (Layout::diagram,
 * bpmn, dmn, drawio, asyncapi) share them, so a page that shows both
 * downloads each package once — and a page with neither never does. Like
 * flow, the packages are external to the bundle and resolved from the
 * host's node_modules.
 */

export type DiagramsModule = typeof import('@dskripchenko/diagrams')
export type DiagramSyntax = NonNullable<Parameters<DiagramsModule['render']>[1]>['syntax']
export type BpmnModule = typeof import('@dskripchenko/bpmn')
export type BpmnVueModule = typeof import('@dskripchenko/bpmn/vue')
export type DrawioModule = typeof import('@dskripchenko/drawio')
export type OpenApiModule = typeof import('@dskripchenko/openapi/vue')

let diagrams: Promise<DiagramsModule> | null = null
let bpmn: Promise<BpmnModule> | null = null
let bpmnVue: Promise<BpmnVueModule> | null = null
let drawio: Promise<DrawioModule> | null = null
let openapi: Promise<OpenApiModule> | null = null

/** A failed download may succeed on the next attempt: the cache forgets it. */
function remember<T>(promise: Promise<T>, forget: () => void): Promise<T> {
  promise.catch(() => {
    forget()
  })
  return promise
}

/** @internal Replaces the dynamic import, for tests. */
export function setDiagramsLoader(loader: (() => Promise<DiagramsModule>) | null): void {
  diagrams = loader ? loader() : null
}

/** @internal Replaces the dynamic imports, for tests. */
export function setNotationLoaders(
  loaders: {
    bpmn?: () => Promise<BpmnModule>
    bpmnVue?: () => Promise<BpmnVueModule>
    drawio?: () => Promise<DrawioModule>
  } | null,
): void {
  bpmn = loaders?.bpmn ? loaders.bpmn() : null
  bpmnVue = loaders?.bpmnVue ? loaders.bpmnVue() : null
  drawio = loaders?.drawio ? loaders.drawio() : null
}

/** @internal Replaces the dynamic import, for tests. */
export function setOpenApiLoader(loader: (() => Promise<OpenApiModule>) | null): void {
  openapi = loader ? loader() : null
}

export function loadDiagrams(): Promise<DiagramsModule> {
  diagrams ??= remember(import('@dskripchenko/diagrams'), () => {
    diagrams = null
  })
  return diagrams
}

/** The BPMN/DMN core: parsing and SVG rendering. */
export function loadBpmn(): Promise<BpmnModule> {
  bpmn ??= remember(import('@dskripchenko/bpmn'), () => {
    bpmn = null
  })
  return bpmn
}

/** The BPMN/DMN viewers (BpmnView, DmnView) with their styles. */
export function loadBpmnVue(): Promise<BpmnVueModule> {
  bpmnVue ??= remember(
    Promise.all([import('@dskripchenko/bpmn/vue'), import('@dskripchenko/bpmn/style.css')]).then(([m]) => m),
    () => {
      bpmnVue = null
    },
  )
  return bpmnVue
}

export function loadDrawio(): Promise<DrawioModule> {
  drawio ??= remember(import('@dskripchenko/drawio'), () => {
    drawio = null
  })
  return drawio
}

/** The OpenAPI / AsyncAPI widgets with their styles. */
export function loadOpenApi(): Promise<OpenApiModule> {
  openapi ??= remember(
    Promise.all([import('@dskripchenko/openapi/vue'), import('@dskripchenko/openapi/style.css')]).then(([m]) => m),
    () => {
      openapi = null
    },
  )
  return openapi
}

/**
 * The document packages, loaded on first use: the diagrams, BPMN/DMN,
 * draw.io, the AsyncAPI reference and the Markdown editor. The fenced blocks of markdown
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
export type MarkdownEditorModule = typeof import('@dskripchenko/markdown-editor')
export type BoardVueModule = typeof import('@dskripchenko/board/vue')

/**
 * The part of @dskripchenko/realtime (the laravel-realtime browser client) the
 * board uses. The host installs it when it runs laravel-realtime; the panel
 * does not depend on it, so its types are described here.
 */
export interface RealtimeChannelLike {
  on(event: string, handler: (data: unknown, meta: { seq?: number }) => void): () => void
  onSubscribed(handler: () => void): () => void
  onResync(handler: (seq: number) => void): () => void
  unsubscribe(): void
}
export interface RealtimeClientLike {
  connect(): Promise<void>
  close(): void
  private(name: string): RealtimeChannelLike
  on(event: 'state', handler: (state: string) => void): () => void
}
export interface RealtimeModule {
  RealtimeClient: new (options: {
    ticketUrl?: string
    headers?: Record<string, string> | (() => Record<string, string>)
    credentials?: RequestCredentials
  }) => RealtimeClientLike
}

let diagrams: Promise<DiagramsModule> | null = null
let bpmn: Promise<BpmnModule> | null = null
let bpmnVue: Promise<BpmnVueModule> | null = null
let drawio: Promise<DrawioModule> | null = null
let openapi: Promise<OpenApiModule> | null = null
let markdownEditor: Promise<MarkdownEditorModule> | null = null
let boardVue: Promise<BoardVueModule> | null = null
let realtime: Promise<RealtimeModule> | null = null

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

/** @internal Replaces the dynamic import, for tests. */
export function setBoardLoaders(loaders: { board?: () => Promise<BoardVueModule>; realtime?: () => Promise<RealtimeModule> } | null): void {
  boardVue = loaders?.board ? loaders.board() : null
  realtime = loaders?.realtime ? loaders.realtime() : null
}

export function setMarkdownEditorLoader(loader: (() => Promise<MarkdownEditorModule>) | null): void {
  markdownEditor = loader ? loader() : null
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

/** The BPMN/DMN viewers and editors (BpmnView, DmnView, BpmnEditor, DmnEditor) with their styles. */
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

/** The Markdown editor (Field\MarkdownEditor) with its styles. */
export function loadMarkdownEditor(): Promise<MarkdownEditorModule> {
  markdownEditor ??= remember(
    Promise.all([import('@dskripchenko/markdown-editor'), import('@dskripchenko/markdown-editor/style.css')]).then(([m]) => m),
    () => {
      markdownEditor = null
    },
  )
  return markdownEditor
}

/** The collaborative board's editor (Layout::board, the board widget) with its styles. */
export function loadBoardVue(): Promise<BoardVueModule> {
  boardVue ??= remember(
    Promise.all([import('@dskripchenko/board/vue'), import('@dskripchenko/board/style.css')]).then(([m]) => m),
    () => {
      boardVue = null
    },
  )
  return boardVue
}

/**
 * The laravel-realtime browser client (@dskripchenko/realtime), when the host
 * registered it with setBoardRealtime(): a board then follows its channel
 * instead of polling. Not registered — rejected, and the board polls.
 */
export function loadRealtime(): Promise<RealtimeModule> {
  return realtime ?? Promise.reject(new Error('@dskripchenko/realtime is not registered: call setBoardRealtime()'))
}

/**
 * Registers the realtime client for boards. The host installs
 * @dskripchenko/realtime and calls this once when the app starts:
 *
 *     import { RealtimeClient } from '@dskripchenko/realtime'
 *     setBoardRealtime(RealtimeClient)
 */
export function setBoardRealtime(client: RealtimeModule['RealtimeClient'] | null): void {
  realtime = client ? Promise.resolve({ RealtimeClient: client }) : null
}

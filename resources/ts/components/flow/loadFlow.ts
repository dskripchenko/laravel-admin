/**
 * @dskripchenko/flow, loaded on first use: the process view (Layout::flow,
 * the infolist entry, ```flow blocks) and the editor (Field\Flow). A page
 * without a process never downloads it. Like the diagrams and the API
 * blocks, the package is external to the bundle and resolved from the host's
 * node_modules.
 */
import { ref, shallowRef, type Ref, type ShallowRef } from 'vue'
import type { Graph } from '@dskripchenko/flow'
import { currentLocale } from '../../stores/i18n'

export type FlowVueModule = typeof import('@dskripchenko/flow/vue')
export type FlowCoreModule = typeof import('@dskripchenko/flow')
export type FlowMermaidModule = typeof import('@dskripchenko/flow/mermaid')

export interface FlowModules {
  vue: FlowVueModule
  core: FlowCoreModule
}

let modules: Promise<FlowModules> | null = null
let mermaid: Promise<FlowMermaidModule> | null = null

/** @internal Replaces the dynamic imports, for tests. */
export function setFlowLoader(loader: (() => Promise<FlowModules>) | null): void {
  modules = loader ? loader() : null
}

/** @internal Replaces the dynamic import of the Mermaid entry, for tests. */
export function setFlowMermaidLoader(loader: (() => Promise<FlowMermaidModule>) | null): void {
  mermaid = loader ? loader() : null
}

export function loadFlow(): Promise<FlowModules> {
  modules ??= Promise.all([
    import('@dskripchenko/flow/vue'),
    import('@dskripchenko/flow'),
    import('@dskripchenko/flow/style.css'),
  ]).then(([vue, core]) => ({ vue, core }))
  // A failed download may succeed on the next page.
  modules.catch(() => {
    modules = null
  })
  return modules
}

/** The Mermaid import of the editor; it pulls in @dskripchenko/diagrams. */
export function loadFlowMermaid(): Promise<FlowMermaidModule> {
  mermaid ??= import('@dskripchenko/flow/mermaid')
  mermaid.catch(() => {
    mermaid = null
  })
  return mermaid
}

/** The panel's language for the package: it speaks English and Russian. */
export function flowLocale(): string {
  return currentLocale().toLowerCase().startsWith('ru') ? 'ru' : 'en'
}

/**
 * A stored value as a model: an object, a JSON string decoded; null for
 * anything else (an empty column, a broken string).
 */
export function toGraph(value: unknown): Graph | null {
  let v = value
  if (typeof v === 'string') {
    if (v.trim() === '') return null
    try {
      v = JSON.parse(v)
    } catch {
      return null
    }
  }
  if (v === null || typeof v !== 'object' || Array.isArray(v)) return null
  const g = v as Partial<Graph>
  if (!Array.isArray(g.nodes)) return null
  // A complete model is returned as is: the editor tells its own changes
  // from the host's by identity.
  if (g.version === 1 && Array.isArray(g.edges)) return g as Graph
  return { ...g, version: 1, nodes: g.nodes, edges: Array.isArray(g.edges) ? g.edges : [] } as Graph
}

/** An empty model, so the package draws its own "empty" message. */
export function blankGraph(): Graph {
  return { version: 1, nodes: [], edges: [] }
}

/** A CSS length: 480 → '480px'; strings pass through. */
export function cssLength(value: number | string | null | undefined, fallback: string): string {
  if (value === null || value === undefined || value === '') return fallback
  return typeof value === 'number' ? `${value}px` : value
}

/**
 * The package for a component: null until it has loaded, with the error of
 * a failed download.
 */
export function useFlowModules(): { modules: ShallowRef<FlowModules | null>; failed: Ref<boolean> } {
  const loaded = shallowRef<FlowModules | null>(null)
  const failed = ref(false)
  loadFlow().then(
    (m) => {
      loaded.value = m
    },
    () => {
      failed.value = true
    },
  )
  return { modules: loaded, failed }
}

/**
 * The search over a form's fields (Tabs::searchable()): the query a tab set
 * holds, and which fields — by their label, hint, placeholder or key — it
 * finds. A tab set counts the finds per tab and dims the tabs without any;
 * a field that does not match hides, a block left without fields hides too,
 * a field that does is marked.
 */
import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'

export interface FieldSearchContext {
  /** The query, trimmed and lowered; '' — no search. */
  query: ComputedRef<string>
}

const KEY: InjectionKey<FieldSearchContext> = Symbol.for('admin.field-search')

export function provideFieldSearch(query: Ref<string>): FieldSearchContext {
  const ctx: FieldSearchContext = { query: computed(() => query.value.trim().toLocaleLowerCase()) }
  provide(KEY, ctx)
  return ctx
}

export function useFieldSearch(): FieldSearchContext | null {
  return inject(KEY, null)
}

type Node = Record<string, unknown>

function text(v: unknown): string {
  return typeof v === 'string' ? v.toLocaleLowerCase() : ''
}

/** Whether a field node matches the (lowered) query. */
export function fieldMatches(node: Node, q: string): boolean {
  if (q === '') return true
  const attrs = (node.attributes ?? {}) as Node
  return [node.label, node.help, node.placeholder, node.name, attrs.help, attrs.title]
    .some((v) => text(v).includes(q))
}

/** How many fields of the nodes — at any depth — match the query. */
export function countMatches(nodes: unknown, q: string): number {
  if (Array.isArray(nodes)) return nodes.reduce<number>((n, x) => n + countMatches(x, q), 0)
  if (!nodes || typeof nodes !== 'object') return 0
  const node = nodes as Node
  if (node.kind === 'field') return fieldMatches(node, q) ? 1 : 0
  let n = 0
  for (const value of Object.values(node)) {
    if (Array.isArray(value)) n += countMatches(value, q)
  }
  return n
}

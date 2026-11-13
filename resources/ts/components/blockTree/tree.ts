/**
 * The block tree of `Layout::blockTree(...)` and the operations on it.
 *
 * A node is `{ref, type, data, children, meta}` (see the backend's
 * BlockTree). The operations change the list they are given in place — it is
 * the screen state's reactive array — and refuse a placement the types do
 * not allow: a top-level node must be a root type, a child one its parent
 * accepts, and no block may stand deeper than `maxDepth`.
 */

export type BlockRef = string | number

export interface BlockNode {
  ref: BlockRef
  type: string
  data: Record<string, unknown> | null
  children: BlockNode[]
  meta?: Record<string, unknown> | null
}

export interface BlockTypeDef {
  type: string
  label: string
  icon?: string | null
  description?: string | null
  group?: string | null
  thumbnail?: string | null
  root?: boolean
  accepts: string[]
  titleFrom?: string[]
  rendersOnSite?: boolean
  defaults?: Record<string, unknown>
  fields: Record<string, unknown>[]
}

export interface TreeRules {
  types: Record<string, BlockTypeDef>
  rootTypes: string[]
  maxDepth: number | null
}

export interface BlockTreeValue {
  nodes: BlockNode[]
  fingerprint?: string | null
  published?: BlockNode[] | null
  savedAt?: string | null
  previewUrl?: string | null
  [key: string]: unknown
}

/** Where a node stands. */
export interface NodeLocation {
  node: BlockNode
  parent: BlockNode | null
  siblings: BlockNode[]
  index: number
  /** 1 for a top-level node. */
  depth: number
}

/** A removed node and where it stood, for the undo. */
export interface RemovedNode {
  node: BlockNode
  parentRef: BlockRef | null
  index: number
}

export function sameRef(a: BlockRef | null | undefined, b: BlockRef | null | undefined): boolean {
  return a !== null && a !== undefined && b !== null && b !== undefined && String(a) === String(b)
}

export function makeRules(types: BlockTypeDef[], rootTypes?: string[] | null, maxDepth?: number | null): TreeRules {
  const map: Record<string, BlockTypeDef> = {}
  for (const t of types) map[t.type] = t
  return {
    types: map,
    rootTypes: rootTypes ?? types.filter((t) => t.root).map((t) => t.type),
    maxDepth: maxDepth ?? null,
  }
}

/** A temporary ref for a new block: `new-` and a random part, unlike any id. */
export function newRef(): string {
  const random =
    typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID().slice(0, 8)
      : Math.random().toString(36).slice(2, 10)
  return `new-${random}`
}

export function locate(nodes: BlockNode[], ref: BlockRef, parent: BlockNode | null = null, depth = 1): NodeLocation | null {
  for (let index = 0; index < nodes.length; index++) {
    const node = nodes[index]
    if (sameRef(node.ref, ref)) return { node, parent, siblings: nodes, index, depth }
    const found = locate(node.children ?? [], ref, node, depth + 1)
    if (found) return found
  }
  return null
}

export function findNode(nodes: BlockNode[], ref: BlockRef | null | undefined): BlockNode | null {
  if (ref === null || ref === undefined) return null
  return locate(nodes, ref)?.node ?? null
}

/** The node and its ancestors, from the top. */
export function pathTo(nodes: BlockNode[], ref: BlockRef): BlockNode[] {
  for (const node of nodes) {
    if (sameRef(node.ref, ref)) return [node]
    const below = pathTo(node.children ?? [], ref)
    if (below.length > 0) return [node, ...below]
  }
  return []
}

/** How many levels the node takes: 1 for a leaf. */
export function height(node: BlockNode): number {
  let max = 0
  for (const child of node.children ?? []) max = Math.max(max, height(child))
  return 1 + max
}

export function countNodes(nodes: BlockNode[]): number {
  let n = 0
  for (const node of nodes) n += 1 + countNodes(node.children ?? [])
  return n
}

function contains(node: BlockNode, ref: BlockRef): boolean {
  return sameRef(node.ref, ref) || (node.children ?? []).some((c) => contains(c, ref))
}

/**
 * May a block of `childType` stand under `parentType` (null — the top level)
 * at `depth`, with `levels` levels of its own?
 */
export function canPlace(
  rules: TreeRules,
  parentType: string | null,
  childType: string,
  depth: number,
  levels = 1,
): boolean {
  if (!rules.types[childType]) return false
  if (parentType === null) {
    if (!rules.rootTypes.includes(childType)) return false
  } else {
    const parent = rules.types[parentType]
    if (!parent || !parent.accepts.includes(childType)) return false
  }
  return rules.maxDepth === null || depth + levels - 1 <= rules.maxDepth
}

/** The types a new block may have under `parentType` at `depth`, in the declared order. */
export function allowedTypes(rules: TreeRules, parentType: string | null, depth: number): BlockTypeDef[] {
  return Object.values(rules.types).filter((t) => canPlace(rules, parentType, t.type, depth))
}

/** Whether the node stands where the rules allow (data from elsewhere may break them). */
export function isPlacedByRules(rules: TreeRules, nodes: BlockNode[], ref: BlockRef): boolean {
  const at = locate(nodes, ref)
  if (!at) return false
  return canPlace(rules, at.parent?.type ?? null, at.node.type, at.depth)
}

function childrenOf(nodes: BlockNode[], parentRef: BlockRef | null): { list: BlockNode[]; parent: BlockNode | null; depth: number } | null {
  if (parentRef === null) return { list: nodes, parent: null, depth: 1 }
  const at = locate(nodes, parentRef)
  if (!at) return null
  if (!Array.isArray(at.node.children)) at.node.children = []
  return { list: at.node.children, parent: at.node, depth: at.depth + 1 }
}

/** Inserts `node` under `parentRef` (null — the top level) at `index`; false when not allowed. */
export function insertNode(
  nodes: BlockNode[],
  rules: TreeRules,
  parentRef: BlockRef | null,
  index: number,
  node: BlockNode,
): boolean {
  const target = childrenOf(nodes, parentRef)
  if (!target) return false
  if (!canPlace(rules, target.parent?.type ?? null, node.type, target.depth, height(node))) return false
  const at = Math.max(0, Math.min(index, target.list.length))
  target.list.splice(at, 0, node)
  return true
}

/** Whether `ref` may move under `parentRef` — not into itself, within the rules. */
export function canMove(nodes: BlockNode[], rules: TreeRules, ref: BlockRef, parentRef: BlockRef | null): boolean {
  const from = locate(nodes, ref)
  if (!from) return false
  if (parentRef !== null && contains(from.node, parentRef)) return false
  const target = parentRef === null ? { parent: null, depth: 1 } : (() => {
    const at = locate(nodes, parentRef)
    return at ? { parent: at.node, depth: at.depth + 1 } : null
  })()
  if (!target) return false
  return canPlace(rules, target.parent?.type ?? null, from.node.type, target.depth, height(from.node))
}

/**
 * Moves `ref` under `parentRef` to `index` — the index among the siblings as
 * they stand BEFORE the move (a drop between two rows). False when not
 * allowed; the tree is unchanged then.
 */
export function moveNode(
  nodes: BlockNode[],
  rules: TreeRules,
  ref: BlockRef,
  parentRef: BlockRef | null,
  index: number,
): boolean {
  if (!canMove(nodes, rules, ref, parentRef)) return false
  const from = locate(nodes, ref)!
  const target = childrenOf(nodes, parentRef)!
  let at = Math.max(0, Math.min(index, target.list.length))
  if (target.list === from.siblings && from.index < at) at -= 1
  from.siblings.splice(from.index, 1)
  target.list.splice(at, 0, from.node)
  return true
}

/** A deep copy with fresh refs everywhere; `meta` is the server's and is not copied. */
export function cloneWithNewRefs(node: BlockNode, makeRef: () => BlockRef = newRef): BlockNode {
  return {
    ref: makeRef(),
    type: node.type,
    data: node.data === null || node.data === undefined ? null : (JSON.parse(JSON.stringify(node.data)) as Record<string, unknown>),
    children: (node.children ?? []).map((c) => cloneWithNewRefs(c, makeRef)),
  }
}

/** Duplicates `ref` with its children, right after it; returns the copy. */
export function duplicateNode(nodes: BlockNode[], ref: BlockRef, makeRef: () => BlockRef = newRef): BlockNode | null {
  const at = locate(nodes, ref)
  if (!at) return null
  const copy = cloneWithNewRefs(at.node, makeRef)
  at.siblings.splice(at.index + 1, 0, copy)
  return copy
}

export function removeNode(nodes: BlockNode[], ref: BlockRef): RemovedNode | null {
  const at = locate(nodes, ref)
  if (!at) return null
  at.siblings.splice(at.index, 1)
  return { node: at.node, parentRef: at.parent?.ref ?? null, index: at.index }
}

/** Puts a removed node back where it stood. */
export function restoreNode(nodes: BlockNode[], removed: RemovedNode): boolean {
  const target = childrenOf(nodes, removed.parentRef)
  if (!target) return false
  target.list.splice(Math.min(removed.index, target.list.length), 0, removed.node)
  return true
}

/** A new block of a type: its defaults, no children. */
export function createNode(def: BlockTypeDef, makeRef: () => BlockRef = newRef): BlockNode {
  return {
    ref: makeRef(),
    type: def.type,
    data: JSON.parse(JSON.stringify(def.defaults ?? {})) as Record<string, unknown>,
    children: [],
  }
}

/** Text for a caption: no tags, no runs of spaces, at most `limit` characters. */
export function plainText(value: unknown, limit = 80): string {
  if (typeof value !== 'string' && typeof value !== 'number') return ''
  const text = String(value)
    .replace(/<br\s*\/?>|<\/p>/gi, ' ')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/\s+/g, ' ')
    .trim()
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text
}

/**
 * The node's caption: the first non-empty `titleFrom` value of its data, else
 * the server's `meta.title`, else '' (the caller shows the type's label).
 */
export function nodeTitle(node: BlockNode, def: BlockTypeDef | undefined): string {
  for (const key of def?.titleFrom ?? []) {
    const text = plainText(node.data?.[key])
    if (text !== '') return text
  }
  return plainText(node.meta?.title)
}

/** JSON with the object keys sorted: equal data, equal text. */
export function stableJson(value: unknown): string {
  return JSON.stringify(value, (_key, v: unknown) => {
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      const sorted: Record<string, unknown> = {}
      for (const k of Object.keys(v as Record<string, unknown>).sort()) sorted[k] = (v as Record<string, unknown>)[k]
      return sorted
    }
    return v
  })
}

export type NodeStatus = 'new' | 'changed'

export interface TreeDiff {
  status: Map<string, NodeStatus>
  removed: number
  /** New, changed and removed blocks together. */
  changes: number
}

interface Placement {
  type: string
  data: string
  parent: string
  index: number
}

function index(nodes: BlockNode[], parent: string, into: Map<string, Placement>): void {
  nodes.forEach((node, i) => {
    into.set(String(node.ref), { type: node.type, data: stableJson(node.data ?? null), parent, index: i })
    index(node.children ?? [], String(node.ref), into)
  })
}

/**
 * The tree against the published one: a block not there is new; one whose
 * data, parent or position differ is changed; a published one gone is
 * removed.
 */
export function diffTrees(nodes: BlockNode[], base: BlockNode[]): TreeDiff {
  const before = new Map<string, Placement>()
  const now = new Map<string, Placement>()
  index(base, '', before)
  index(nodes, '', now)
  const status = new Map<string, NodeStatus>()
  for (const [ref, at] of now) {
    const was = before.get(ref)
    if (!was) status.set(ref, 'new')
    else if (was.type !== at.type || was.data !== at.data || was.parent !== at.parent || was.index !== at.index) {
      status.set(ref, 'changed')
    }
  }
  let removed = 0
  for (const ref of before.keys()) if (!now.has(ref)) removed++
  return { status, removed, changes: status.size + removed }
}

export function cloneNodes(nodes: BlockNode[]): BlockNode[] {
  return JSON.parse(JSON.stringify(nodes ?? [])) as BlockNode[]
}

/** The value as it came from the state, made safe to work on. */
export function normalizeValue(raw: unknown): BlockTreeValue {
  const value = raw && typeof raw === 'object' && !Array.isArray(raw) ? (raw as BlockTreeValue) : ({ nodes: [] } as BlockTreeValue)
  if (!Array.isArray(value.nodes)) value.nodes = []
  const fix = (list: BlockNode[]): void => {
    for (const node of list) {
      if (!Array.isArray(node.children)) node.children = []
      fix(node.children)
    }
  }
  fix(value.nodes)
  return value
}

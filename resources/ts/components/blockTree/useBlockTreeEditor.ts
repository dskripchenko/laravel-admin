/**
 * The editing of a block tree (`Layout::blockTree(...)`): the value lives in
 * the screen's state under `source`, so the screen's methods get it with the
 * rest of the state; this composable changes it in place and talks to the
 * screen methods the layout names — the draft (autosave), publish and
 * discard — through the screen context's `call` (the screen's runMethod).
 *
 * A method answers with `extra.blockTree` (see the backend's BlockTree::saved,
 * ::conflict, ::invalid):
 *
 *   - `value`    — the value to go on from (the published tree, a reloaded draft);
 *   - `savedAt`  — when the draft was saved;
 *   - `conflict` — `{message, differences, fingerprint}`: nothing was published;
 *   - `errors`   — `[{ref, path, field, message}]`: the nodes to fix.
 *
 * A ValidationError keyed by the tree's path (`content.nodes.0.children.1.data.title`)
 * is mapped onto the nodes the same way.
 */
import { computed, onBeforeUnmount, reactive, ref, toRaw, watch, type ComputedRef, type Ref } from 'vue'
import { tryUseFormState } from '../render/formState'
import { useScreenContext } from '../render/screenContext'
import { confirmDialog } from '../../composables/useConfirm'
import { adminToast, toastError } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'
import { ValidationError } from '../../api/errors'
import type { ScreenMethodResult } from '../../stores/screen'
import {
  cloneNodes,
  createNode,
  diffTrees,
  duplicateNode,
  findNode,
  insertNode,
  locate,
  moveNode,
  normalizeValue,
  pathTo,
  removeNode,
  sameRef,
  stableJson,
  type BlockNode,
  type BlockRef,
  type BlockTreeValue,
  type BlockTypeDef,
  type TreeDiff,
  type TreeRules,
} from './tree'

export interface BlockTreeEditorOptions {
  source: string
  rules: () => TreeRules
  draftMethod: () => string | null | undefined
  publishMethod: () => string | null | undefined
  discardMethod: () => string | null | undefined
  autosaveDelay: () => number
  readOnly: () => boolean
}

export type DraftStatus = 'clean' | 'dirty' | 'saving' | 'saved' | 'error' | 'publishing'

export interface BlockTreeConflict {
  message: string
  differences: Array<string | { ref?: BlockRef | null; label?: string; change?: string }>
  fingerprint?: string | null
}

export interface BlockTreeError {
  ref: BlockRef | null
  path: string
  field: string | null
  message: string
}

export interface NodeErrors {
  messages: string[]
  fields: Record<string, string[]>
}

interface BlockTreeAnswer {
  value?: BlockTreeValue | null
  savedAt?: string | null
  conflict?: BlockTreeConflict | null
  errors?: BlockTreeError[] | null
  message?: string | null
}

interface UndoEntry {
  nodes: string
  selected: BlockRef | null
  label: string
}

const UNDO_LIMIT = 50

export interface BlockTreeEditor {
  value: ComputedRef<BlockTreeValue>
  nodes: ComputedRef<BlockNode[]>
  selectedRef: Ref<BlockRef | null>
  selected: ComputedRef<BlockNode | null>
  path: ComputedRef<BlockNode[]>
  diff: ComputedRef<TreeDiff>
  status: Ref<DraftStatus>
  savedAt: Ref<string | null>
  conflict: Ref<BlockTreeConflict | null>
  errors: Ref<Map<string, NodeErrors>>
  undoLabel: ComputedRef<string | null>
  canEdit: ComputedRef<boolean>
  canPublish: ComputedRef<boolean>
  select: (ref: BlockRef | null) => boolean
  add: (def: BlockTypeDef, parentRef: BlockRef | null, index?: number) => BlockNode | null
  move: (ref: BlockRef, parentRef: BlockRef | null, index: number) => boolean
  duplicate: (ref: BlockRef) => BlockNode | null
  remove: (ref: BlockRef) => boolean
  undo: () => boolean
  saveDraft: () => Promise<void>
  publish: () => Promise<boolean>
  discard: () => Promise<boolean>
  /** Replaces the value — from a method's answer, or a reload. */
  reset: (value: BlockTreeValue) => void
}

/** The ref of the node a ValidationError key points to, and the data field, if any. */
export function locateErrorKey(nodes: BlockNode[], key: string): { ref: BlockRef; field: string | null } | null {
  const m = /(?:^|\.)(\d+(?:\.children\.\d+)*)(?:\.data\.(.+))?$/.exec(key)
  if (!m) return null
  const steps = m[1].split('.children.').map(Number)
  let list = nodes
  let node: BlockNode | undefined
  for (const step of steps) {
    node = list[step]
    if (!node) return null
    list = node.children ?? []
  }
  return node ? { ref: node.ref, field: m[2] ?? null } : null
}

export function useBlockTreeEditor(options: BlockTreeEditorOptions): BlockTreeEditor {
  const form = tryUseFormState()
  const call = useScreenContext()?.call
  const local = reactive<Record<string, unknown>>({})

  const read = (): unknown => (form ? form.getField(options.source) : local[options.source])
  const write = (v: BlockTreeValue): void => {
    if (form) form.setField(options.source, v)
    else local[options.source] = v
  }

  const isValue = (raw: unknown): raw is BlockTreeValue =>
    !!raw && typeof raw === 'object' && !Array.isArray(raw) && Array.isArray((raw as BlockTreeValue).nodes)
  if (!isValue(read())) write(normalizeValue(read()))

  const empty: BlockTreeValue = { nodes: [] }
  const value = computed<BlockTreeValue>(() => {
    const raw = read()
    return isValue(raw) ? raw : empty
  })
  const nodes = computed<BlockNode[]>(() => value.value.nodes)

  /** The published tree the marks are counted against. */
  const base = ref<BlockNode[]>([])
  /** The tree as last loaded or saved: no changes since. */
  const savedJson = ref('')
  const savedAt = ref<string | null>(null)
  const status = ref<DraftStatus>('clean')
  const conflict = ref<BlockTreeConflict | null>(null)
  const errors = ref<Map<string, NodeErrors>>(new Map())
  const selectedRef = ref<BlockRef | null>(null)
  const undoStack = ref<UndoEntry[]>([])

  const canEdit = computed(() => !options.readOnly())
  const canPublish = computed(() => canEdit.value && !!options.publishMethod() && !!call)

  function loaded(v: BlockTreeValue): void {
    base.value = cloneNodes(Array.isArray(v.published) ? v.published : v.nodes)
    savedJson.value = stableJson(v.nodes)
    savedAt.value = typeof v.savedAt === 'string' ? v.savedAt : null
    undoStack.value = []
    conflict.value = null
    errors.value = new Map()
    status.value = Array.isArray(v.published) && stableJson(v.published) !== savedJson.value ? 'saved' : 'clean'
    if (selectedRef.value !== null && !findNode(v.nodes, selectedRef.value)) selectedRef.value = null
  }

  // A new value object — the first load, a reload of the screen, a method's
  // answer — starts the editing over.
  // The value reset() has just adopted is not loaded twice.
  let adopted: unknown = null
  watch(
    () => read(),
    (raw) => {
      if (toRaw(raw) === adopted) return
      if (!isValue(raw)) {
        write(normalizeValue(raw))
        return
      }
      normalizeValue(raw)
      loaded(raw)
    },
    { immediate: true },
  )

  const diff = computed(() => diffTrees(nodes.value, base.value))
  const selected = computed(() => findNode(nodes.value, selectedRef.value))
  const path = computed(() => (selectedRef.value === null ? [] : pathTo(nodes.value, selectedRef.value)))
  const undoLabel = computed(() => undoStack.value[undoStack.value.length - 1]?.label ?? null)

  /* ---------------- the draft ---------------- */

  let timer: ReturnType<typeof setTimeout> | null = null
  let inflight: Promise<void> | null = null

  function cancelTimer(): void {
    if (timer !== null) clearTimeout(timer)
    timer = null
  }

  watch(
    () => stableJson(nodes.value),
    (json) => {
      if (json === savedJson.value) {
        cancelTimer()
        if (status.value === 'dirty') status.value = savedAt.value ? 'saved' : 'clean'
        return
      }
      if (status.value !== 'saving' && status.value !== 'publishing') status.value = 'dirty'
      scheduleDraft()
    },
  )

  function scheduleDraft(): void {
    cancelTimer()
    const delay = options.autosaveDelay()
    if (!options.draftMethod() || !call || !canEdit.value || delay <= 0) return
    timer = setTimeout(() => {
      timer = null
      void saveDraft()
    }, delay)
  }

  /** The screen's state with the tree, as a button would send it. */
  function payload(): Record<string, unknown> {
    const state = form ? form.state : local
    return JSON.parse(JSON.stringify(state)) as Record<string, unknown>
  }

  function answerOf(res: ScreenMethodResult): BlockTreeAnswer {
    const extra = res.extra?.blockTree
    const answer: BlockTreeAnswer = extra && typeof extra === 'object' ? { ...(extra as BlockTreeAnswer) } : {}
    const fromState = res.state?.[options.source]
    if (!answer.value && fromState && typeof fromState === 'object') answer.value = fromState as BlockTreeValue
    return answer
  }

  function applyErrors(list: BlockTreeError[]): void {
    const map = new Map<string, NodeErrors>()
    for (const e of list) {
      const ref = e.ref ?? (e.path ? locateErrorKey(nodes.value, e.path)?.ref : null)
      if (ref === null || ref === undefined) continue
      const key = String(ref)
      const entry = map.get(key) ?? { messages: [], fields: {} }
      if (e.field) (entry.fields[e.field] ??= []).push(e.message)
      else entry.messages.push(e.message)
      map.set(key, entry)
    }
    errors.value = map
  }

  function applyValidation(err: ValidationError): boolean {
    const list: BlockTreeError[] = []
    for (const [key, messages] of Object.entries(err.fields)) {
      const at = locateErrorKey(nodes.value, key)
      if (!at) continue
      for (const message of messages) list.push({ ref: at.ref, path: key, field: at.field, message })
    }
    if (list.length === 0) return false
    applyErrors(list)
    return true
  }

  async function saveDraft(): Promise<void> {
    const method = options.draftMethod()
    if (!method || !call || !canEdit.value) return
    if (inflight) await inflight
    const json = stableJson(nodes.value)
    if (json === savedJson.value) return
    cancelTimer()
    status.value = 'saving'
    const run = (async () => {
      try {
        const answer = answerOf(await call(method, [payload()]))
        if (answer.value) {
          reset(answer.value)
        } else {
          savedJson.value = json
        }
        savedAt.value = answer.savedAt ?? answer.value?.savedAt ?? new Date().toISOString()
        status.value = stableJson(nodes.value) === savedJson.value ? 'saved' : 'dirty'
        if (status.value === 'dirty') scheduleDraft()
      } catch (err) {
        status.value = 'error'
        toastError(err, tr('Не удалось сохранить черновик'))
      }
    })()
    inflight = run
    await run
    inflight = null
  }

  async function publish(): Promise<boolean> {
    const method = options.publishMethod()
    if (!method || !call || !canEdit.value) return false
    cancelTimer()
    if (inflight) await inflight
    const before = status.value
    status.value = 'publishing'
    try {
      const res = await call(method, [payload()])
      const answer = answerOf(res)
      if (answer.conflict) {
        conflict.value = {
          message: answer.conflict.message || tr('Опубликованная версия изменилась после начала правки'),
          differences: Array.isArray(answer.conflict.differences) ? answer.conflict.differences : [],
          fingerprint: answer.conflict.fingerprint ?? null,
        }
        status.value = before === 'publishing' ? 'dirty' : before
        return false
      }
      if (answer.errors && answer.errors.length > 0) {
        applyErrors(answer.errors)
        adminToast.error(answer.message || tr('Исправьте ошибки в блоках'))
        status.value = before === 'publishing' ? 'dirty' : before
        return false
      }
      if (answer.value) {
        reset(answer.value)
      } else {
        base.value = cloneNodes(nodes.value)
        savedJson.value = stableJson(nodes.value)
        undoStack.value = []
        errors.value = new Map()
        conflict.value = null
      }
      status.value = 'clean'
      savedAt.value = null
      adminToast.success(res.message || answer.message || tr('Опубликовано'))
      return true
    } catch (err) {
      status.value = before === 'publishing' ? 'dirty' : before
      if (err instanceof ValidationError && applyValidation(err)) {
        adminToast.error(err.message || tr('Исправьте ошибки в блоках'))
        return false
      }
      toastError(err, tr('Не удалось опубликовать'))
      return false
    }
  }

  async function discard(): Promise<boolean> {
    if (!canEdit.value) return false
    const ok = await confirmDialog({
      title: tr('Отменить изменения?'),
      message: tr('Черновик будет удалён, дерево вернётся к опубликованному состоянию.'),
      confirmLabel: tr('Отменить изменения'),
      destructive: true,
    })
    if (!ok) return false
    cancelTimer()
    if (inflight) await inflight
    const method = options.discardMethod()
    if (method && call) {
      try {
        const res = await call(method, [payload()])
        const answer = answerOf(res)
        if (answer.value) {
          reset(answer.value)
          if (res.message) adminToast.success(res.message)
          return true
        }
      } catch (err) {
        toastError(err, tr('Не удалось отменить изменения'))
        return false
      }
    }
    pushUndo(tr('Отмена изменений'))
    nodes.value.splice(0, nodes.value.length, ...cloneNodes(base.value))
    if (method) savedJson.value = stableJson(nodes.value)
    errors.value = new Map()
    conflict.value = null
    return true
  }

  function reset(v: BlockTreeValue): void {
    const next = normalizeValue(JSON.parse(JSON.stringify(v)) as BlockTreeValue)
    adopted = next
    write(next)
    loaded(value.value)
  }

  /* ---------------- the structure ---------------- */

  function pushUndo(label: string): void {
    undoStack.value.push({ nodes: JSON.stringify(nodes.value), selected: selectedRef.value, label })
    if (undoStack.value.length > UNDO_LIMIT) undoStack.value.shift()
  }

  function labelOf(node: BlockNode): string {
    return options.rules().types[node.type]?.label ?? node.type
  }

  function select(ref: BlockRef | null): boolean {
    if (ref === null) {
      selectedRef.value = null
      return true
    }
    const node = findNode(nodes.value, ref)
    if (!node) return false
    selectedRef.value = node.ref
    return true
  }

  function add(def: BlockTypeDef, parentRef: BlockRef | null, index?: number): BlockNode | null {
    if (!canEdit.value) return null
    const node = createNode(def)
    const siblings = parentRef === null ? nodes.value : findNode(nodes.value, parentRef)?.children ?? []
    const snapshot = JSON.stringify(nodes.value)
    if (!insertNode(nodes.value, options.rules(), parentRef, index ?? siblings.length, node)) return null
    undoStack.value.push({ nodes: snapshot, selected: selectedRef.value, label: tr('Добавление блока') })
    selectedRef.value = node.ref
    return findNode(nodes.value, node.ref)
  }

  function move(ref: BlockRef, parentRef: BlockRef | null, index: number): boolean {
    if (!canEdit.value) return false
    const snapshot = JSON.stringify(nodes.value)
    if (!moveNode(nodes.value, options.rules(), ref, parentRef, index)) return false
    if (JSON.stringify(nodes.value) === snapshot) return true
    undoStack.value.push({ nodes: snapshot, selected: selectedRef.value, label: tr('Перемещение блока') })
    return true
  }

  function duplicate(ref: BlockRef): BlockNode | null {
    if (!canEdit.value) return null
    const snapshot = JSON.stringify(nodes.value)
    const copy = duplicateNode(nodes.value, ref)
    if (!copy) return null
    undoStack.value.push({ nodes: snapshot, selected: selectedRef.value, label: tr('Дублирование блока') })
    selectedRef.value = copy.ref
    return findNode(nodes.value, copy.ref)
  }

  function remove(ref: BlockRef): boolean {
    if (!canEdit.value) return false
    const at = locate(nodes.value, ref)
    if (!at) return false
    pushUndo(`${tr('Удалён блок')} «${labelOf(at.node)}»`)
    removeNode(nodes.value, ref)
    if (selectedRef.value !== null && (sameRef(selectedRef.value, ref) || !findNode(nodes.value, selectedRef.value))) {
      selectedRef.value = at.parent?.ref ?? null
    }
    return true
  }

  function undo(): boolean {
    const entry = undoStack.value.pop()
    if (!entry) return false
    nodes.value.splice(0, nodes.value.length, ...(JSON.parse(entry.nodes) as BlockNode[]))
    selectedRef.value = entry.selected !== null && findNode(nodes.value, entry.selected) ? entry.selected : null
    return true
  }

  onBeforeUnmount(() => {
    // An unsaved change is saved on the way out rather than lost.
    if (timer !== null) {
      cancelTimer()
      void saveDraft()
    }
  })

  return {
    value,
    nodes,
    selectedRef,
    selected,
    path,
    diff,
    status,
    savedAt,
    conflict,
    errors,
    undoLabel,
    canEdit,
    canPublish,
    select,
    add,
    move,
    duplicate,
    remove,
    undo,
    saveDraft,
    publish,
    discard,
    reset,
  }
}

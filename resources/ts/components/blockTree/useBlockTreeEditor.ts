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
 *   - `refs`, `fingerprint` — the draft is saved (BlockTree::draftSaved): the
 *     new blocks' temporary refs → their ids, and the new fingerprint; the
 *     tree is kept as it is, with what was typed while the request was out;
 *   - `nodes`    — with draftSaved: `{ref: {data?, meta?}}`, what the server
 *     made of some nodes (a file's temporary path made permanent, a preset
 *     turned into a full block): applied to those nodes, but a field typed in
 *     while the request was out keeps the input (see applyNodeUpdate);
 *   - `savedAt`  — when the draft was saved;
 *   - `conflict` — `{message, differences, fingerprint}`: nothing was saved;
 *   - `errors`   — `[{ref, path, field, message}]`: the nodes to fix.
 *
 * A conflict or errors in the draft's answer pause the autosave: after errors
 * — until the next change; after a conflict — until the person saves over it
 * (saveOver) or the editing starts over (publish, discard, a reload).
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
  applyNodeUpdate,
  childrenFit,
  cloneNodes,
  createFromPreset,
  createNode,
  diffTrees,
  duplicateNode,
  findNode,
  insertNode,
  canRemove,
  fieldMetaOf,
  isLocked,
  locate,
  moveNode,
  normalizeValue,
  pathTo,
  removeNode,
  renameRefs,
  sameRef,
  stableJson,
  type BlockNode,
  type BlockRef,
  type BlockTreeValue,
  type BlockPresetDef,
  type BlockTypeDef,
  type NodeUpdate,
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
  /** What was stopped: the publish (the default) or the draft's save. */
  kind?: 'publish' | 'draft'
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
  /** BlockTree::draftSaved: temporary ref → the block's id. */
  refs?: Record<string, BlockRef> | BlockRef[] | null
  fingerprint?: string | null
  /** BlockTree::draftSaved(nodes: ...): the server's data and meta of some nodes, by ref. */
  nodes?: Record<string, NodeUpdate> | null
}

/** Why the autosave waits: a conflict (until saveOver) or errors (until the next change). */
export type DraftPause = 'conflict' | 'invalid' | null

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
  paused: Ref<DraftPause>
  select: (ref: BlockRef | null) => boolean
  add: (def: BlockTypeDef, parentRef: BlockRef | null, index?: number) => BlockNode | null
  /** Inserts a block made from a palette preset: its type, data, meta and children, with fresh refs. */
  addPreset: (preset: BlockPresetDef, parentRef: BlockRef | null, index?: number) => BlockNode | null
  move: (ref: BlockRef, parentRef: BlockRef | null, index: number) => boolean
  duplicate: (ref: BlockRef) => BlockNode | null
  remove: (ref: BlockRef) => boolean
  undo: () => boolean
  /** Puts the base block's value back into a field (`meta.fields[name].base`). */
  revertField: (ref: BlockRef, name: string) => boolean
  saveDraft: () => Promise<void>
  /** Saves the draft over a conflict: takes the conflict's fingerprint and saves. */
  saveOver: () => Promise<void>
  /** Hides the conflict's message; a paused autosave stays paused. */
  dismissConflict: () => void
  /** Called with the renamed refs (old → new) after a draft is saved. */
  onRefsRenamed: (listener: (map: Map<string, BlockRef>) => void) => void
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
  const paused = ref<DraftPause>(null)
  /** Whose errors are shown: a draft's are cleared by the next saved draft. */
  let errorsFrom: 'draft' | 'publish' | null = null
  /** The fingerprint a draft conflict offered, kept when its message is dismissed. */
  let overFingerprint: string | null = null
  const renameListeners: Array<(map: Map<string, BlockRef>) => void> = []

  const canEdit = computed(() => !options.readOnly())
  const canPublish = computed(() => canEdit.value && !!options.publishMethod() && !!call)

  function loaded(v: BlockTreeValue): void {
    base.value = cloneNodes(Array.isArray(v.published) ? v.published : v.nodes)
    savedJson.value = stableJson(v.nodes)
    savedAt.value = typeof v.savedAt === 'string' ? v.savedAt : null
    undoStack.value = []
    conflict.value = null
    errors.value = new Map()
    errorsFrom = null
    overFingerprint = null
    paused.value = null
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
      if (paused.value === 'invalid') paused.value = null
      if (status.value !== 'saving' && status.value !== 'publishing' && paused.value === null) status.value = 'dirty'
      scheduleDraft()
    },
  )

  function scheduleDraft(): void {
    cancelTimer()
    const delay = options.autosaveDelay()
    if (paused.value !== null) return
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
    if (!answer.value && answer.refs === undefined && fromState && typeof fromState === 'object') answer.value = fromState as BlockTreeValue
    return answer
  }

  function applyErrors(list: BlockTreeError[], from: 'draft' | 'publish' = 'publish'): void {
    errorsFrom = from
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

  function applyValidation(err: ValidationError, from: 'draft' | 'publish' = 'publish'): boolean {
    const list: BlockTreeError[] = []
    for (const [key, messages] of Object.entries(err.fields)) {
      const at = locateErrorKey(nodes.value, key)
      if (!at) continue
      for (const message of messages) list.push({ ref: at.ref, path: key, field: at.field, message })
    }
    if (list.length === 0) return false
    applyErrors(list, from)
    return true
  }

  function conflictOf(c: BlockTreeConflict, kind: 'publish' | 'draft'): BlockTreeConflict {
    return {
      kind,
      message:
        c.message ||
        (kind === 'draft' ? tr('Черновик изменился после начала правки') : tr('Опубликованная версия изменилась после начала правки')),
      differences: Array.isArray(c.differences) ? c.differences : [],
      fingerprint: c.fingerprint ?? null,
    }
  }

  function refMapOf(refs: BlockTreeAnswer['refs']): Map<string, BlockRef> {
    const map = new Map<string, BlockRef>()
    if (!refs || typeof refs !== 'object' || Array.isArray(refs)) return map
    for (const [from, to] of Object.entries(refs)) {
      if ((typeof to === 'string' && to !== '') || typeof to === 'number') map.set(from, to)
    }
    return map
  }

  /**
   * A draft saved with BlockTree::draftSaved: the refs of the new blocks are
   * renamed in the tree as it is now — with whatever was typed meanwhile —
   * and in the snapshot that was sent, which becomes the saved one.
   */
  function adoptDraft(sentJson: string, answer: BlockTreeAnswer): void {
    const map = refMapOf(answer.refs)
    const sent = JSON.parse(sentJson) as BlockNode[]
    if (map.size > 0) {
      renameRefs(sent, map)
      renameRefs(nodes.value, map)
      const renamed = (r: BlockRef | null): BlockRef | null => (r === null ? null : map.get(String(r)) ?? r)
      selectedRef.value = renamed(selectedRef.value)
      if (errors.value.size > 0) {
        const next = new Map<string, NodeErrors>()
        for (const [key, entry] of errors.value) next.set(String(map.get(key) ?? key), entry)
        errors.value = next
      }
      // An undo must not bring back a temporary ref: the block would be saved twice.
      for (const entry of undoStack.value) {
        const list = JSON.parse(entry.nodes) as BlockNode[]
        if (renameRefs(list, map) > 0) entry.nodes = JSON.stringify(list)
        entry.selected = renamed(entry.selected)
      }
      for (const listener of renameListeners) listener(map)
    }
    applyNodeUpdates(sent, answer.nodes, map)
    savedJson.value = stableJson(sent)
    if (typeof answer.fingerprint === 'string' && answer.fingerprint !== '') value.value.fingerprint = answer.fingerprint
  }

  /**
   * The server's data and meta of some nodes (`nodes` of draftSaved), keyed
   * by the ref — the new one, or the temporary one it was renamed from.
   */
  function applyNodeUpdates(sent: BlockNode[], updates: BlockTreeAnswer['nodes'], map: Map<string, BlockRef>): void {
    if (!updates || typeof updates !== 'object' || Array.isArray(updates)) return
    for (const [key, update] of Object.entries(updates)) {
      if (!update || typeof update !== 'object' || Array.isArray(update)) continue
      const ref = map.get(key) ?? key
      const sentNode = findNode(sent, ref)
      const current = findNode(nodes.value, ref)
      if (current) applyNodeUpdate(current, sentNode, update)
      else if (sentNode) applyNodeUpdate(sentNode, null, update)
    }
  }

  function stopDraft(why: Exclude<DraftPause, null>): void {
    paused.value = why
    status.value = 'error'
    cancelTimer()
  }

  async function saveDraft(): Promise<void> {
    const method = options.draftMethod()
    if (!method || !call || !canEdit.value) return
    if (inflight) await inflight
    const json = stableJson(nodes.value)
    if (json === savedJson.value) return
    cancelTimer()
    paused.value = null
    status.value = 'saving'
    const run = (async () => {
      try {
        const answer = answerOf(await call(method, [payload()]))
        if (answer.conflict) {
          conflict.value = conflictOf(answer.conflict, 'draft')
          overFingerprint = conflict.value.fingerprint ?? null
          stopDraft('conflict')
          return
        }
        if (answer.errors && answer.errors.length > 0) {
          applyErrors(answer.errors, 'draft')
          adminToast.error(answer.message || tr('Черновик не сохранён: исправьте ошибки в блоках'))
          stopDraft('invalid')
          return
        }
        if (answer.value) {
          reset(answer.value)
        } else if (answer.refs !== undefined || typeof answer.fingerprint === 'string') {
          adoptDraft(json, answer)
        } else {
          savedJson.value = json
        }
        if (errorsFrom === 'draft') {
          errors.value = new Map()
          errorsFrom = null
        }
        if (conflict.value?.kind === 'draft') conflict.value = null
        savedAt.value = answer.savedAt ?? answer.value?.savedAt ?? new Date().toISOString()
        status.value = stableJson(nodes.value) === savedJson.value ? 'saved' : 'dirty'
        if (status.value === 'dirty') scheduleDraft()
      } catch (err) {
        if (err instanceof ValidationError && applyValidation(err, 'draft')) {
          adminToast.error(err.message || tr('Черновик не сохранён: исправьте ошибки в блоках'))
          stopDraft('invalid')
          return
        }
        status.value = 'error'
        toastError(err, tr('Не удалось сохранить черновик'))
      }
    })()
    inflight = run
    await run
    inflight = null
  }

  async function saveOver(): Promise<void> {
    const fingerprint = conflict.value?.kind === 'draft' ? conflict.value.fingerprint : overFingerprint
    if (typeof fingerprint === 'string' && fingerprint !== '') value.value.fingerprint = fingerprint
    overFingerprint = null
    conflict.value = null
    paused.value = null
    await saveDraft()
  }

  function dismissConflict(): void {
    conflict.value = null
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
        conflict.value = conflictOf(answer.conflict, 'publish')
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
        errorsFrom = null
        conflict.value = null
        paused.value = null
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
    errorsFrom = null
    conflict.value = null
    paused.value = null
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

  function addPreset(preset: BlockPresetDef, parentRef: BlockRef | null, index?: number): BlockNode | null {
    if (!canEdit.value) return null
    const node = createFromPreset(preset)
    const target = parentRef === null ? null : locate(nodes.value, parentRef)
    if (parentRef !== null && !target) return null
    // The children of a preset follow the rules too: none is let in by it.
    if (!childrenFit(options.rules(), node, target ? target.depth + 1 : 1)) return null
    const siblings = target ? target.node.children ?? [] : nodes.value
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
    if (!at || !canRemove(nodes.value, ref)) return false
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

  function revertField(ref: BlockRef, name: string): boolean {
    if (!canEdit.value) return false
    const node = findNode(nodes.value, ref)
    if (!node || isLocked(node)) return false
    const meta = fieldMetaOf(node, name)
    if (!meta) return false
    pushUndo(tr('Возврат значения основы'))
    if (!node.data || typeof node.data !== 'object' || Array.isArray(node.data)) node.data = {}
    if (meta.base === undefined) delete node.data[name]
    else node.data[name] = JSON.parse(JSON.stringify(meta.base)) as unknown
    // The server may read it: the field goes back to the base, even when the
    // override happened to equal it.
    meta.inherited = true
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
    paused,
    select,
    add,
    addPreset,
    move,
    duplicate,
    remove,
    undo,
    revertField,
    saveDraft,
    saveOver,
    dismissConflict,
    onRefsRenamed: (listener) => {
      renameListeners.push(listener)
    },
    publish,
    discard,
    reset,
  }
}

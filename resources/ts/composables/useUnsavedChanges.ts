/**
 * Unsaved changes of a form: which fields changed — named by where they sit,
 * «Капча › Ключ сервера» — the mark on a changed field, and the question
 * before the page is left with them.
 */
import { computed, inject, onBeforeUnmount, onMounted, provide, type ComputedRef, type InjectionKey } from 'vue'
import { matchedRouteKey, onBeforeRouteLeave } from 'vue-router'
import { confirmDialog } from './useConfirm'
import { trSafe as tr } from '../stores/i18n'

export interface ChangedField {
  name: string
  label: string
  /** The tabs and blocks above the field, then its label. */
  path: string[]
}

type Node = Record<string, unknown>

function same(a: unknown, b: unknown): boolean {
  const norm = (v: unknown): string => JSON.stringify(v === undefined || v === '' ? null : v) ?? 'null'
  return norm(a) === norm(b)
}

/** A deep copy of a state: the baseline the changes are measured against. */
export function snapshot(state: Record<string, unknown>): Record<string, unknown> {
  try {
    return JSON.parse(JSON.stringify(state)) as Record<string, unknown>
  } catch {
    return { ...state }
  }
}

/**
 * The fields of the layout whose value differs from the baseline, in the
 * layout's order, each with the trail of tab and block titles above it.
 */
export function changedFields(
  nodes: unknown,
  state: Record<string, unknown>,
  baseline: Record<string, unknown>,
): ChangedField[] {
  const out: ChangedField[] = []
  const seen = new Set<string>()

  function walk(node: unknown, trail: string[]): void {
    if (Array.isArray(node)) {
      for (const child of node) walk(child, trail)
      return
    }
    if (!node || typeof node !== 'object') return
    const n = node as Node
    if (n.kind === 'field') {
      const name = typeof n.name === 'string' ? n.name : ''
      if (name !== '' && !seen.has(name) && !same(state[name], baseline[name])) {
        seen.add(name)
        const label = typeof n.label === 'string' && n.label !== '' ? n.label : name
        out.push({ name, label, path: [...trail, label] })
      }
      return
    }
    // A tab carries a label and items; a block or a section, a title.
    const own = n.kind !== 'layout' && typeof n.label === 'string' && Array.isArray(n.items)
      ? n.label
      : typeof n.title === 'string' && n.title !== '' ? n.title : null
    const next = own ? [...trail, own] : trail
    for (const value of Object.values(n)) {
      if (Array.isArray(value)) walk(value, next)
    }
  }

  walk(nodes, [])
  return out
}

const CHANGED: InjectionKey<ComputedRef<ReadonlySet<string>>> = Symbol.for('admin.changed-fields')

/** Hands the names of the changed fields down to the field renderers: they mark them. */
export function provideChangedFields(names: ComputedRef<ReadonlySet<string>>): void {
  provide(CHANGED, names)
}

export function useChangedFields(): ComputedRef<ReadonlySet<string>> | null {
  return inject(CHANGED, null)
}

/**
 * Asks before the page is left with unsaved changes: a move inside the panel
 * gets the panel's confirmation, closing or reloading the tab the browser's.
 * `skipNext()` lets one move through — after the page asked by itself.
 */
export function useLeaveGuard(dirty: () => boolean): { skipNext: () => void } {
  let skip = false

  // Only under a route the router rendered: a page mounted by hand (a test, a
  // host's own page) has no route to leave.
  const guarded = inject(matchedRouteKey, null) !== null
  if (guarded) onBeforeRouteLeave(async () => {
    if (skip) {
      skip = false
      return true
    }
    if (!dirty()) return true
    return confirmDialog({
      title: tr('Несохранённые изменения'),
      message: tr('Несохранённые изменения будут потеряны. Продолжить?'),
      confirmLabel: tr('Уйти без сохранения'),
      destructive: true,
    })
  })

  function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (!dirty()) return
    event.preventDefault()
    event.returnValue = ''
  }
  onMounted(() => {
    if (typeof window !== 'undefined') window.addEventListener('beforeunload', onBeforeUnload)
  })
  onBeforeUnmount(() => {
    if (typeof window !== 'undefined') window.removeEventListener('beforeunload', onBeforeUnload)
  })

  return { skipNext: () => { skip = true } }
}

/** The changed fields as a set of names, for provideChangedFields(). */
export function namesOf(list: ComputedRef<ChangedField[]>): ComputedRef<ReadonlySet<string>> {
  return computed(() => new Set(list.value.map((f) => f.name)))
}

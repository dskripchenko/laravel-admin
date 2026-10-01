/**
 * useActionRunner — one dispatcher for every action type the backend declares
 * (src/Action): button, bulk, modal, async, link and dropdown.
 *
 * The page supplies an executor that knows how to run a server-side action in
 * its own context — a resource posts `{key, ids, payload}` to
 * `/{slug}/action`, a screen calls `runMethod` — and the runner handles the
 * rest the same way everywhere:
 *
 *   - confirm   → a confirmation dialog (title + message), not window.confirm
 *   - modal     → a form dialog built from the action's fields; its values go
 *                 as `payload`, a 422 shows the errors next to the fields
 *   - async     → `delayed/run`, then `delayed/status` polling with progress
 *   - link      → router navigation for in-panel paths, a full navigation,
 *                 a new window or a download otherwise
 *   - dropdown  → nothing to run itself; its items are dispatched one by one
 *
 * The dialogs themselves are drawn by AdminActionDialogs, which takes the
 * runner as a prop.
 */
import { getCurrentScope, onScopeDispose, reactive, ref } from 'vue'
import { useRouter, type Router } from 'vue-router'
import { getAdminClient } from '../stores/registry'
import { ValidationError } from '../api/errors'
import { adminToast } from '../stores/toast'
import { trSafe as tr, tRaw } from '../stores/i18n'

export interface ActionConfirm {
  message: string
  title?: string
  confirmLabel?: string
  cancelLabel?: string
}

/** An action as Action::toArray() serializes it, normalized. */
export interface AdminAction {
  name: string
  label: string
  type: string
  icon: string | null
  confirm: ActionConfirm | null
  primary: boolean
  destructive: boolean
  position: string[]
  attributes: Record<string, unknown>
  /** The nested actions of a dropdown. */
  items: AdminAction[]
}

/** How a page runs a server-side action in its own context. */
export interface ActionExecutor {
  /** Runs a button, bulk or modal action; `payload` is the modal form's values. */
  execute: (action: AdminAction, payload?: Record<string, unknown>) => Promise<unknown>
  /** The record keys the actions apply to, when the context has any. */
  ids?: () => Array<string | number>
  /** After a successful execute: a toast, a reload. */
  onSuccess?: (action: AdminAction, result: unknown) => void | Promise<void>
  /** When execute fails; a toast with the error's message by default. */
  onError?: (action: AdminAction, err: unknown) => void
  /** Reloads the context's data after an async action has finished. */
  refresh?: () => void | Promise<void>
}

export interface DelayedStatus {
  uuid?: string
  status: string
  progress?: number | null
  data?: unknown
  error?: string | null
}

/** The delayed-process statuses after which nothing changes any more. */
const TERMINAL = new Set(['done', 'error', 'expired', 'cancelled'])

function normalizeConfirm(raw: unknown): ActionConfirm | null {
  if (typeof raw === 'string') return raw === '' ? null : { message: raw }
  if (raw && typeof raw === 'object') {
    const c = raw as Record<string, unknown>
    if (typeof c.message !== 'string' || c.message === '') return null
    return {
      message: c.message,
      title: typeof c.title === 'string' ? c.title : undefined,
      confirmLabel: typeof c.confirmLabel === 'string' ? c.confirmLabel : undefined,
      cancelLabel: typeof c.cancelLabel === 'string' ? c.cancelLabel : undefined,
    }
  }
  return null
}

/** Normalizes one serialized action; null when it has no name or label. */
export function normalizeAction(raw: unknown): AdminAction | null {
  if (!raw || typeof raw !== 'object') return null
  const a = raw as Record<string, unknown>
  const name = String(a.name ?? a.key ?? '')
  const label = String(a.label ?? name)
  if (name === '' || label === '') return null
  const attributes = (a.attributes && typeof a.attributes === 'object'
    ? a.attributes
    : {}) as Record<string, unknown>
  return {
    name,
    label,
    type: typeof a.type === 'string' ? a.type : 'button',
    icon: typeof a.icon === 'string' ? a.icon : null,
    confirm: normalizeConfirm(a.confirm),
    primary: Boolean(a.primary),
    destructive: Boolean(a.destructive),
    position: Array.isArray(a.position) ? (a.position as string[]) : [],
    attributes,
    items: normalizeActions(a.items),
  }
}

export function normalizeActions(raw: unknown): AdminAction[] {
  if (!Array.isArray(raw)) return []
  return raw.map(normalizeAction).filter((a): a is AdminAction => a !== null)
}

/** Row and bulk actions apply to the selected records. */
export function needsSelection(action: AdminAction): boolean {
  return action.position.includes('row') || action.position.includes('bulk')
}

/**
 * Whether a selection of `count` records satisfies the action's
 * requiresAtLeast / requiresAtMost (BulkAction).
 */
export function selectionAllows(action: AdminAction, count: number): boolean {
  const min = Number(action.attributes.requiresAtLeast ?? 0)
  const max = action.attributes.requiresAtMost
  if (Number.isFinite(min) && count < min) return false
  if (max !== undefined && max !== null && count > Number(max)) return false
  return true
}

/** The UidModal sizes; the backend's modalSize() is a free-form string. */
export function modalSizeOf(raw: unknown): 'sm' | 'md' | 'lg' | 'xl' {
  const s = String(raw ?? '').toLowerCase()
  if (s === 'sm' || s === 'small') return 'sm'
  if (s === 'lg' || s === 'large') return 'lg'
  if (s === 'xl' || s === 'extra' || s === 'full' || s === 'fullscreen') return 'xl'
  return 'md'
}

/**
 * Turns an absolute href into a router path when it points inside the panel;
 * null when it has to be a full navigation.
 */
export function toRouterPath(router: Router | undefined, href: string): string | null {
  if (!router || !href.startsWith('/') || href.startsWith('//')) return null
  const base = (router.options.history.base ?? '').replace(/\/+$/, '')
  if (base !== '') {
    if (href === base) return '/'
    if (href.startsWith(`${base}/`) || href.startsWith(`${base}?`)) return href.slice(base.length)
  }
  // A path written relative to the panel ('/r/posts'): in-panel only when a
  // real route matches it, not the catch-all 404.
  const resolved = router.resolve(href)
  const matched = resolved.matched.length > 0
    && !resolved.matched.some((r) => r.path.includes('pathMatch'))
  return matched ? href : null
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function errorMessage(err: unknown, fallback: string): string {
  if (err instanceof ValidationError) return err.firstFieldMessage() ?? err.message ?? fallback
  if (err instanceof Error && err.message) return err.message
  return fallback
}

/** The field errors keyed by the field name — `payload.x` becomes `x`. */
function fieldErrors(err: ValidationError): Record<string, string[]> {
  const out: Record<string, string[]> = {}
  for (const [key, messages] of Object.entries(err.fields)) {
    out[key.startsWith('payload.') ? key.slice('payload.'.length) : key] = messages
  }
  return out
}

export function useActionRunner(executor: ActionExecutor) {
  // useRouter only works inside a component with a router installed.
  let router: Router | undefined
  try {
    router = useRouter()
  } catch {
    router = undefined
  }

  /** A server-side action is running. */
  const running = ref(false)

  /* ---------------- confirmation ---------------- */

  const confirmState = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    destructive: false,
  })
  let confirmResolve: ((ok: boolean) => void) | null = null

  /** Asks for a confirmation; resolves to true when the user agreed. */
  function confirm(opts: ActionConfirm | string, destructive = false): Promise<boolean> {
    const c = typeof opts === 'string' ? { message: opts } : opts
    // A second question replaces an unanswered one, which counts as a no.
    confirmResolve?.(false)
    confirmState.title = c.title ?? tr('Подтверждение')
    confirmState.message = c.message
    confirmState.confirmLabel = c.confirmLabel ?? tr('Подтвердить')
    confirmState.cancelLabel = c.cancelLabel ?? tr('Отмена')
    confirmState.destructive = destructive
    confirmState.open = true
    return new Promise<boolean>((resolve) => {
      confirmResolve = resolve
    })
  }

  function resolveConfirm(ok: boolean): void {
    confirmState.open = false
    const resolve = confirmResolve
    confirmResolve = null
    resolve?.(ok)
  }

  /* ---------------- modal form ---------------- */

  const modalState = reactive({
    open: false,
    /** Bumped on every open, so the form remounts with a fresh state. */
    seq: 0,
    action: null as AdminAction | null,
    title: '',
    submitLabel: '',
    size: 'md' as 'sm' | 'md' | 'lg' | 'xl',
    fields: [] as Array<Record<string, unknown>>,
    values: {} as Record<string, unknown>,
    errors: {} as Record<string, string[]>,
    submitting: false,
  })

  function openModal(action: AdminAction): void {
    const fields = Array.isArray(action.attributes.fields)
      ? (action.attributes.fields as Array<Record<string, unknown>>)
      : []
    const values: Record<string, unknown> = {}
    for (const f of fields) {
      if (typeof f.name === 'string' && f.defaultValue !== undefined && f.defaultValue !== null) {
        values[f.name] = f.defaultValue
      }
    }
    modalState.action = action
    modalState.title = typeof action.attributes.modalTitle === 'string'
      ? action.attributes.modalTitle
      : action.label
    modalState.submitLabel = typeof action.attributes.submitLabel === 'string'
      ? action.attributes.submitLabel
      : tr('Выполнить')
    modalState.size = modalSizeOf(action.attributes.modalSize)
    modalState.fields = fields
    modalState.values = values
    modalState.errors = {}
    modalState.submitting = false
    modalState.seq++
    modalState.open = true
  }

  function closeModal(): void {
    modalState.open = false
    modalState.action = null
  }

  async function submitModal(): Promise<void> {
    const action = modalState.action
    if (!action || modalState.submitting) return
    modalState.submitting = true
    modalState.errors = {}
    running.value = true
    try {
      const result = await executor.execute(action, { ...modalState.values })
      closeModal()
      await executor.onSuccess?.(action, result)
    } catch (err) {
      if (err instanceof ValidationError) {
        // The form stays open with the errors next to the fields.
        modalState.errors = fieldErrors(err)
        const unplaced = Object.keys(modalState.errors).filter(
          (k) => !modalState.fields.some((f) => f.name === k),
        )
        if (unplaced.length > 0 || Object.keys(modalState.errors).length === 0) {
          adminToast.error(errorMessage(err, tr('Проверьте заполнение формы.')))
        }
      } else {
        reportError(action, err)
      }
    } finally {
      modalState.submitting = false
      running.value = false
    }
  }

  /* ---------------- async (delayed process) ---------------- */

  const asyncState = reactive({
    open: false,
    label: '',
    status: '',
    progress: 0,
    error: null as string | null,
    finished: false,
  })
  let disposed = false
  if (getCurrentScope()) {
    onScopeDispose(() => {
      disposed = true
    })
  }

  function closeAsync(): void {
    // Closing only hides the dialog: the polling goes on and the result still
    // arrives as a toast.
    asyncState.open = false
  }

  async function runAsync(action: AdminAction): Promise<void> {
    const handler = action.attributes.handler as { entity?: string; method?: string } | undefined
    if (!handler?.entity || !handler.method) {
      adminToast.error(tRaw('Действие «:action» не настроено.', { action: action.label }))
      return
    }
    const params: Record<string, unknown> = {
      ...((action.attributes.params as Record<string, unknown> | undefined) ?? {}),
    }
    if (executor.ids && needsSelection(action)) params.ids = executor.ids()
    const body: Record<string, unknown> = {
      entity: handler.entity,
      method: handler.method,
      params,
    }
    if (typeof action.attributes.callback === 'string') body.callback = action.attributes.callback

    const intervalMs = Math.max(1, Number(action.attributes.pollInterval ?? 2) || 2) * 1000
    const client = getAdminClient()

    Object.assign(asyncState, {
      open: true,
      label: action.label,
      status: 'new',
      progress: 0,
      error: null,
      finished: false,
    })

    let status: DelayedStatus
    try {
      const started = await client.post<{ uuid: string; status: string }>('/delayed/run', body)
      status = { status: started.status, progress: 0 }
      while (!TERMINAL.has(status.status)) {
        await sleep(intervalMs)
        if (disposed) return
        status = await client.get<DelayedStatus>(
          `/delayed/status?uuid=${encodeURIComponent(started.uuid)}`,
        )
        asyncState.status = status.status
        asyncState.progress = Math.max(0, Math.min(100, Number(status.progress ?? 0)))
      }
    } catch (err) {
      asyncState.finished = true
      asyncState.error = errorMessage(err, tr('Не удалось выполнить действие.'))
      adminToast.error(tRaw('Не удалось выполнить действие «:action».', { action: action.label }))
      return
    }

    asyncState.finished = true
    if (status.status === 'done') {
      asyncState.progress = 100
      const data = status.data as Record<string, unknown> | null | undefined
      const message = data && typeof data === 'object' && typeof data.message === 'string'
        ? data.message
        : tRaw('Действие «:action» выполнено.', { action: action.label })
      adminToast.success(message)
      await executor.refresh?.()
    } else {
      asyncState.error = status.error ?? tr('Процесс завершился с ошибкой.')
      adminToast.error(tRaw('Действие «:action» завершилось с ошибкой: :error', {
        action: action.label,
        error: asyncState.error,
      }))
    }
  }

  /* ---------------- link ---------------- */

  function openLink(action: AdminAction): void {
    const href = typeof action.attributes.href === 'string' ? action.attributes.href : ''
    if (href === '') return
    const target = typeof action.attributes.target === 'string' ? action.attributes.target : '_self'
    const download = action.attributes.download

    if (download !== undefined && download !== false && typeof document !== 'undefined') {
      const a = document.createElement('a')
      a.href = href
      a.download = typeof download === 'string' ? download : ''
      a.rel = 'noopener'
      document.body.appendChild(a)
      a.click()
      a.remove()
      return
    }
    if (target !== '_self') {
      window.open(href, target, 'noopener')
      return
    }
    const path = toRouterPath(router, href)
    if (path !== null && router) {
      router.push(path).catch(() => undefined)
      return
    }
    window.location.assign(href)
  }

  /* ---------------- dispatch ---------------- */

  function reportError(action: AdminAction, err: unknown): void {
    if (executor.onError) {
      executor.onError(action, err)
      return
    }
    adminToast.error(errorMessage(err, tRaw('Не удалось выполнить действие «:action».', { action: action.label })))
  }

  /** Runs any action according to its type. */
  async function run(action: AdminAction): Promise<void> {
    if (action.type === 'dropdown') return

    if (executor.ids && needsSelection(action) && !selectionAllows(action, executor.ids().length)) {
      adminToast.warning(tRaw('Действие «:action» недоступно для выбранного числа записей.', { action: action.label }))
      return
    }

    if (action.confirm && !(await confirm(action.confirm, action.destructive))) return

    if (action.type === 'link') {
      openLink(action)
      return
    }
    if (action.type === 'modal') {
      openModal(action)
      return
    }
    if (action.type === 'async') {
      await runAsync(action)
      return
    }

    running.value = true
    try {
      const result = await executor.execute(action)
      await executor.onSuccess?.(action, result)
    } catch (err) {
      reportError(action, err)
    } finally {
      running.value = false
    }
  }

  return {
    run,
    running,
    confirm,
    confirmState,
    resolveConfirm,
    modalState,
    submitModal,
    closeModal,
    asyncState,
    closeAsync,
  }
}

export type ActionRunner = ReturnType<typeof useActionRunner>

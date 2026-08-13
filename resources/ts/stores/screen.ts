/**
 * useScreenStore — the state of an arbitrary screen: custom forms and pages
 * outside of CRUD.
 *
 * It holds:
 *   - the slug and the state (the form's working copy, exposed through
 *     provideFormState)
 *   - the layout, command bar, name and description — the snapshot from the
 *     backend's `state` action
 *   - the field-keyed errors, set from a ValidationError out of runMethod
 *   - the loading and running UX flags
 *
 * The endpoints, per the laravel-admin contract:
 *   GET    /{slug}/state              — the compile() snapshot
 *   POST   /{slug}/runMethod          — dispatches a command method
 *
 * Nothing is cached between slugs: switching to another screen resets the
 * state. Keeping a dirty state across screens is not needed — custom forms are
 * usually an atomic submit, not a resumable black box.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { ApiError, ValidationError } from '../api/errors'

export interface ScreenLayoutNode {
  kind: 'layout' | 'field'
  type?: string
  name?: string
  children?: ScreenLayoutNode[]
  [key: string]: unknown
}

export interface ScreenAction {
  kind: 'action'
  name: string
  label: string
  type: string
  icon?: string | null
  primary?: boolean
  destructive?: boolean
  permission?: string | null
  confirm?: { message: string; title?: string } | null
  position?: string[]
  attributes?: Record<string, unknown>
}

export interface ScreenStateSnapshot {
  state: Record<string, unknown>
  name: string
  description: string | null
  layout: ScreenLayoutNode[]
  command_bar: ScreenAction[]
  permissions: string[]
  etag: string
}

/** A link offered alongside a screen's message — see `lastMessageLink`. */
export interface ScreenMessageLink {
  url: string
  label: string
}

export interface ScreenMethodResult {
  state?: Record<string, unknown>
  message?: string
  /**
   * Where the message leads. A screen that starts background work has
   * somewhere to send the person — the job's own page — and a bare sentence
   * leaves them to find it by themselves.
   */
  message_link?: ScreenMessageLink | null
  alerts?: Array<{ type: string; message: string; duration_ms?: number }>
  redirect_url?: string | null
  refresh?: boolean
  download_url?: string | null
  extra?: Record<string, unknown>
}

export const useScreenStore = defineStore('admin-screen', () => {
  const slug = ref<string | null>(null)
  const name = ref<string>('')
  const description = ref<string | null>(null)
  const layout = ref<ScreenLayoutNode[]>([])
  const commandBar = ref<ScreenAction[]>([])
  const permissions = ref<string[]>([])
  const etag = ref<string | null>(null)

  /** The form's working copy, exposed through provideFormState. */
  const state = ref<Record<string, unknown>>({})
  /** The initial state from the last state fetch, used by reset. */
  const initial = ref<Record<string, unknown>>({})
  /** The field-keyed errors from a ValidationException. */
  const errors = ref<Record<string, string[]>>({})

  const loading = ref(false)
  const running = ref(false)
  const error = ref<Error | null>(null)
  const lastMessage = ref<string | null>(null)
  /** Set together with `lastMessage`; cleared everywhere it is. */
  const lastMessageLink = ref<ScreenMessageLink | null>(null)

  const hasError = computed(() => error.value !== null)

  /** An in-place mutation: it keeps the identity of the provide/inject reactive proxy. */
  function replaceObject(target: Record<string, unknown>, next: Record<string, unknown>): void {
    for (const k of Object.keys(target)) delete target[k]
    Object.assign(target, next)
  }

  /**
   * Normalizes the layout tree for the frontend's LayoutRenderer.
   *
   * The backend's Layout::toArray() puts the children into `children`, while
   * the frontend layout components (Rows, Columns, Section, Tabs) expect
   * `items`. We alias it recursively, without mutating the backend format.
   */
  function normalizeLayoutTree(nodes: ScreenLayoutNode[]): ScreenLayoutNode[] {
    return nodes.map((node) => normalizeLayoutNode(node))
  }

  function normalizeLayoutNode(node: ScreenLayoutNode): ScreenLayoutNode {
    const next: ScreenLayoutNode = { ...node }
    if (Array.isArray(node.children) && node.items === undefined) {
      next.items = (node.children as ScreenLayoutNode[]).map((child) =>
        typeof child === 'object' && child !== null ? normalizeLayoutNode(child) : child,
      )
    } else if (Array.isArray(node.items)) {
      next.items = (node.items as ScreenLayoutNode[]).map((child) =>
        typeof child === 'object' && child !== null ? normalizeLayoutNode(child) : child,
      )
    }
    // The backend puts the type-specific fields into `props`. We unpack them
    // to the top level, since the frontend layouts read them directly as the
    // component's props.
    if (node.props && typeof node.props === 'object') {
      Object.assign(next, node.props)
    }
    return next
  }

  function reset(): void {
    slug.value = null
    name.value = ''
    description.value = null
    layout.value = []
    commandBar.value = []
    permissions.value = []
    etag.value = null
    replaceObject(state.value, {})
    replaceObject(initial.value, {})
    errors.value = {}
    loading.value = false
    running.value = false
    error.value = null
    lastMessage.value = null
    lastMessageLink.value = null
  }

  /** Loads the screen snapshot. */
  async function load(screenSlug: string, params?: Record<string, unknown>): Promise<void> {
    // Moving to ANOTHER screen clears the previous banner; reloading the same
    // screen (res.refresh after runMethod) does not, or it would swallow the
    // message that just arrived.
    if (slug.value !== screenSlug) {
      lastMessage.value = null
    lastMessageLink.value = null
    }
    slug.value = screenSlug
    loading.value = true
    error.value = null
    errors.value = {}

    try {
      const client = getAdminClient()
      const res = await client.get<ScreenStateSnapshot>(`/${screenSlug}/state`, {
        params,
      })
      name.value = res.name
      description.value = res.description
      layout.value = normalizeLayoutTree(res.layout)
      commandBar.value = res.command_bar
      permissions.value = res.permissions
      etag.value = res.etag
      replaceObject(state.value, res.state)
      replaceObject(initial.value, res.state)
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Dispatches one of the screen's command methods, passing the current state
   * as `payload`. On success it updates the state from the answer and sets
   * lastMessage; on a ValidationError it fills in the errors; on anything else
   * it sets error and rethrows.
   */
  async function runMethod(
    method: string,
    overridePayload?: Record<string, unknown>,
  ): Promise<ScreenMethodResult> {
    if (!slug.value) {
      throw new Error('useScreenStore.runMethod() before slug set')
    }
    if (running.value) {
      throw new Error('Already running a method')
    }

    running.value = true
    error.value = null
    errors.value = {}
    lastMessage.value = null
    lastMessageLink.value = null

    try {
      const client = getAdminClient()
      const payload = overridePayload ?? state.value
      const res = await client.post<ScreenMethodResult>(`/${slug.value}/runMethod`, {
        method,
        payload,
      })

      if (res.state && Object.keys(res.state).length > 0) {
        replaceObject(state.value, res.state)
        replaceObject(initial.value, { ...res.state })
      }
      if (res.message) {
        lastMessage.value = res.message
        lastMessageLink.value = res.message_link ?? null
      }
      if (res.download_url && typeof document !== 'undefined') {
        // The server hands the file over at a signed URL, with
        // Content-Disposition: attachment, so we trigger the download through
        // a programmatic anchor. The field was declared in the contract but
        // never handled: the screens' "Download…" buttons quietly did nothing.
        const a = document.createElement('a')
        a.href = res.download_url
        a.rel = 'noopener'
        document.body.appendChild(a)
        a.click()
        a.remove()
      }
      if (res.refresh) {
        // The server asked for the snapshot to be reloaded — do it lazily.
        await load(slug.value).catch(() => undefined)
      }
      return res
    } catch (err) {
      if (err instanceof ValidationError) {
        errors.value = { ...err.fields }
      } else if (err instanceof ApiError) {
        error.value = err
      } else if (err instanceof Error) {
        error.value = err
      }
      throw err
    } finally {
      running.value = false
    }
  }

  function setField(name: string, value: unknown): void {
    state.value[name] = value
    if (errors.value[name]) {
      const next = { ...errors.value }
      delete next[name]
      errors.value = next
    }
  }

  function setErrors(next: Record<string, string[]>): void {
    errors.value = { ...next }
  }

  function clearErrors(): void {
    errors.value = {}
  }

  return {
    // state
    slug,
    name,
    description,
    layout,
    commandBar,
    permissions,
    etag,
    state,
    initial,
    errors,
    loading,
    running,
    error,
    lastMessage,
    lastMessageLink,
    // getters
    hasError,
    // actions
    reset,
    load,
    runMethod,
    setField,
    setErrors,
    clearErrors,
  }
})

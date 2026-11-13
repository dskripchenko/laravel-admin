/**
 * The screen context: what a layout deep in the tree may ask of the screen it
 * sits on.
 *
 * Two things live here:
 *
 *   - the overlays — the open/closed state of the Modal and Drawer layouts,
 *     keyed by the layout's id. An action carrying `attributes.opens` (see the
 *     backend's `Action::opens()`) opens the overlay with that id;
 *   - the dispatcher — a click on an action, wherever it is drawn (the command
 *     bar, a modal's footer, a wizard's submit), goes through `dispatch`, which
 *     asks for the confirmation and opens an overlay, or hands the action to
 *     the page's action runner (`run`) — or, without one, calls the screen's
 *     method.
 *
 * ScreenPage provides it. Outside a screen — a resource form, a unit test —
 * `useScreenContext()` returns null and the layouts degrade gracefully: an
 * overlay keeps its own local state and a submit has nowhere to go.
 */

import { inject, provide, reactive, type InjectionKey, type Ref } from 'vue'
import { confirmDialog } from '../../composables/useConfirm'
import type { ScreenMethodResult } from '../../stores/screen'

/** The shape of an action as the backend serializes it (Action::toArray). */
export interface ScreenActionLike {
  name?: string
  label?: string
  type?: string
  icon?: string | null
  primary?: boolean
  destructive?: boolean
  confirm?: { message: string; title?: string } | null
  attributes?: Record<string, unknown>
}

export interface ScreenContext {
  isOpen: (id: string) => boolean
  open: (id: string) => void
  close: (id: string) => void
  /** Whether a screen method is running right now. */
  running: Readonly<Ref<boolean>>
  /**
   * Calls a screen method with the current state — and the button's own
   * parameters, when it has any. Resolves to true on success, false on a
   * failure — the errors land in the screen store.
   */
  runMethod: (method: string, params?: Record<string, unknown>) => Promise<boolean>
  /**
   * Runs an action: the confirmation first, then either `attributes.opens`
   * (an overlay) or `attributes.method` (a screen method). Resolves to true
   * when the action went through.
   */
  dispatch: (action: ScreenActionLike) => Promise<boolean>
  /**
   * Calls a screen method with positional `parameters` and resolves to its
   * raw answer, leaving the page alone: no state merge, no busy flag, no
   * alert. For layouts that talk to the screen on their own — a process
   * view polling its monitoring, say. Rejects with the ApiError of a failed
   * call. Absent outside a screen page.
   */
  call?: ScreenMethodCaller
}

export type ScreenMethodCaller = (
  method: string,
  parameters?: unknown[],
  options?: { signal?: AbortSignal },
) => Promise<ScreenMethodResult>

const ScreenContextKey: InjectionKey<ScreenContext> = Symbol('admin.screen-context')

export interface ScreenContextOptions {
  running: Readonly<Ref<boolean>>
  runMethod: (method: string, params?: Record<string, unknown>) => Promise<boolean>
  /** The confirmation prompt; the panel's confirmation dialog by default. */
  confirm?: (confirm: { message: string; title?: string }) => boolean | Promise<boolean>
  /**
   * Runs any action that does not open an overlay — confirmation included.
   * ScreenPage passes its action runner here, so the layouts' actions get
   * every action type (modal, async, link…) too. Without it only
   * `attributes.method` is understood.
   */
  run?: (action: ScreenActionLike) => Promise<boolean>
  /** See ScreenContext.call. */
  call?: ScreenMethodCaller
}

export function createScreenContext(options: ScreenContextOptions): ScreenContext {
  const openIds = reactive(new Set<string>())
  const ask = options.confirm ?? ((c: { message: string; title?: string }) => confirmDialog(c))

  const ctx: ScreenContext = {
    isOpen: (id) => openIds.has(id),
    open: (id) => {
      openIds.add(id)
    },
    close: (id) => {
      openIds.delete(id)
    },
    running: options.running,
    runMethod: options.runMethod,
    call: options.call,
    async dispatch(action) {
      const opens = action.attributes?.opens
      if (typeof opens !== 'string' || opens === '') {
        if (options.run) return options.run(action)
      }

      if (action.confirm?.message && !(await ask(action.confirm))) return false

      if (typeof opens === 'string' && opens !== '') {
        ctx.open(opens)
        return true
      }

      const method = action.attributes?.method
      if (typeof method === 'string' && method !== '') {
        const params = buttonParams(action)
        return params ? options.runMethod(method, params) : options.runMethod(method)
      }

      return false
    },
  }

  return ctx
}

export function provideScreenContext(options: ScreenContextOptions): ScreenContext {
  const ctx = createScreenContext(options)
  provide(ScreenContextKey, ctx)
  return ctx
}

/**
 * The parameters a button carries (the backend's Button::parameters()), sent
 * with the state as `params`; undefined for a button without any.
 */
export function buttonParams(action: { attributes?: Record<string, unknown> }): Record<string, unknown> | undefined {
  const params = action.attributes?.parameters
  if (!params || typeof params !== 'object') return undefined
  // An empty PHP array arrives as [] — no parameters.
  if (Array.isArray(params)) return params.length > 0 ? { ...params } : undefined
  return params as Record<string, unknown>
}

/** The screen context, or null outside a screen. */
export function useScreenContext(): ScreenContext | null {
  return inject(ScreenContextKey, null)
}

/** For tests and hosts that render layouts outside ScreenPage. */
export { ScreenContextKey }

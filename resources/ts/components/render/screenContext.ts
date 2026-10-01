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
   * Calls a screen method with the current state. Resolves to true on
   * success, false on a failure — the errors land in the screen store.
   */
  runMethod: (method: string) => Promise<boolean>
  /**
   * Runs an action: the confirmation first, then either `attributes.opens`
   * (an overlay) or `attributes.method` (a screen method). Resolves to true
   * when the action went through.
   */
  dispatch: (action: ScreenActionLike) => Promise<boolean>
}

const ScreenContextKey: InjectionKey<ScreenContext> = Symbol('admin.screen-context')

export interface ScreenContextOptions {
  running: Readonly<Ref<boolean>>
  runMethod: (method: string) => Promise<boolean>
  /** The confirmation prompt; the panel's confirmation dialog by default. */
  confirm?: (confirm: { message: string; title?: string }) => boolean | Promise<boolean>
  /**
   * Runs any action that does not open an overlay — confirmation included.
   * ScreenPage passes its action runner here, so the layouts' actions get
   * every action type (modal, async, link…) too. Without it only
   * `attributes.method` is understood.
   */
  run?: (action: ScreenActionLike) => Promise<boolean>
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
        return options.runMethod(method)
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

/** The screen context, or null outside a screen. */
export function useScreenContext(): ScreenContext | null {
  return inject(ScreenContextKey, null)
}

/** For tests and hosts that render layouts outside ScreenPage. */
export { ScreenContextKey }

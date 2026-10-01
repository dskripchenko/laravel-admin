/**
 * What the Modal and Drawer layouts share: the open state and the footer.
 *
 * The open state lives in the screen context, keyed by the layout's id, so an
 * action anywhere on the screen can open the overlay (`Action::opens($id)` on
 * the backend). Outside a screen the overlay falls back to a local flag.
 *
 * A non-dismissable overlay ignores every close request coming from the UI kit
 * (the cross, the overlay click, Escape); only its own footer actions — a
 * successful method, or an action named `close`/`cancel` — close it.
 */

import { computed, ref, type ComputedRef, type WritableComputedRef } from 'vue'
import { useScreenContext, type ScreenActionLike } from '../render/screenContext'

export interface OverlayProps {
  id?: string | null
  /** Open on first render — for an overlay that has no trigger. */
  open?: boolean
  dismissable?: boolean
  footer?: ScreenActionLike[]
}

export type OverlayButtonVariant = 'primary' | 'danger' | 'secondary'

export interface Overlay {
  model: WritableComputedRef<boolean>
  running: ComputedRef<boolean>
  close: () => void
  onFooterClick: (action: ScreenActionLike) => Promise<void>
  variantOf: (action: ScreenActionLike) => OverlayButtonVariant
}

const CLOSE_NAMES = new Set(['close', 'cancel'])

export function useOverlay(props: OverlayProps): Overlay {
  const screen = useScreenContext()
  const local = ref<boolean>(props.open === true)

  if (screen && props.id && props.open === true) screen.open(props.id)

  function isOpen(): boolean {
    return screen && props.id ? screen.isOpen(props.id) : local.value
  }

  function setOpen(value: boolean): void {
    if (screen && props.id) {
      if (value) screen.open(props.id)
      else screen.close(props.id)
    } else {
      local.value = value
    }
  }

  const model = computed<boolean>({
    get: isOpen,
    set(value) {
      // The UI kit asks to close; a non-dismissable overlay says no.
      if (!value && props.dismissable === false) return
      setOpen(value)
    },
  })

  async function onFooterClick(action: ScreenActionLike): Promise<void> {
    const method = action.attributes?.method
    const opens = action.attributes?.opens
    const closes = action.attributes?.close === true || CLOSE_NAMES.has(action.name ?? '')

    if (closes && !method && !opens) {
      setOpen(false)
      return
    }
    if (!screen) return

    const ok = await screen.dispatch(action)
    // A method that went through has done the overlay's job; opening another
    // overlay from a footer means moving on to it.
    if (ok) setOpen(false)
  }

  function variantOf(action: ScreenActionLike): OverlayButtonVariant {
    if (action.destructive) return 'danger'
    if (action.primary) return 'primary'
    return 'secondary'
  }

  return {
    model,
    running: computed(() => screen?.running.value ?? false),
    close: () => setOpen(false),
    onFooterClick,
    variantOf,
  }
}

/**
 * useReveal — the panel-wide dialog for a value an action shows once (a new
 * token, a password): it stays open until closed and has a copy button,
 * where a toast would be gone before the value is copied.
 *
 * The state is a module singleton, as useConfirm's: AdminRevealDialog
 * (mounted once by AdminApp) draws it. A resource action answers with
 * `payload.reveal` — `{ value, label }` — and its page calls revealOnce().
 */
import { reactive } from 'vue'

export interface RevealOptions {
  value: string
  title?: string
  /** What happened, in words — the action's message. */
  message?: string
  /** The caption of the value. */
  label?: string | null
}

export interface RevealState {
  open: boolean
  title: string
  message: string
  label: string
  value: string
}

export const revealState = reactive<RevealState>({
  open: false,
  title: '',
  message: '',
  label: '',
  value: '',
})

let closed: (() => void) | null = null

/** Shows the value; resolves when the dialog is closed. */
export function revealOnce(opts: RevealOptions): Promise<void> {
  closed?.()
  revealState.title = opts.title ?? ''
  revealState.message = opts.message ?? ''
  revealState.label = opts.label ?? ''
  revealState.value = opts.value
  revealState.open = true
  return new Promise<void>((resolve) => {
    closed = resolve
  })
}

/** Closes the dialog and forgets the value. */
export function closeReveal(): void {
  revealState.open = false
  revealState.value = ''
  const resolve = closed
  closed = null
  resolve?.()
}

/** `reveal` of an action's answer, if it carries one. */
export function actionReveal(raw: unknown): { value: string; label: string | null } | null {
  const reveal = (raw as { reveal?: { value?: unknown; label?: unknown } } | null | undefined)?.reveal
  if (!reveal || typeof reveal.value !== 'string' || reveal.value === '') return null
  return { value: reveal.value, label: typeof reveal.label === 'string' ? reveal.label : null }
}

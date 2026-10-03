/**
 * useConfirm — the panel-wide confirmation dialog, in place of window.confirm.
 *
 * The state is a module singleton: any component calls `confirm(...)` and
 * awaits the answer, and AdminConfirmDialog (mounted once by AdminApp) draws
 * the question. Pages that run actions keep their own runner's dialog
 * (useActionRunner); this is for everything else — a delete button, a reset,
 * a revoke.
 */
import { reactive } from 'vue'
import { trSafe as tr } from '../stores/i18n'

export interface ConfirmOptions {
  message: string
  title?: string
  confirmLabel?: string
  cancelLabel?: string
  /** Paints the confirm button as dangerous. */
  destructive?: boolean
}

export interface ConfirmState {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  cancelLabel: string
  destructive: boolean
}

export const confirmState = reactive<ConfirmState>({
  open: false,
  title: '',
  message: '',
  confirmLabel: '',
  cancelLabel: '',
  destructive: false,
})

let pending: ((ok: boolean) => void) | null = null

/** Asks a question; resolves to true when the user agreed. */
export function confirmDialog(opts: ConfirmOptions | string): Promise<boolean> {
  const c = typeof opts === 'string' ? { message: opts } : opts
  // A second question replaces an unanswered one, which counts as a no.
  pending?.(false)
  confirmState.title = c.title ?? tr('Подтверждение')
  confirmState.message = c.message
  confirmState.confirmLabel = c.confirmLabel ?? tr('Подтвердить')
  confirmState.cancelLabel = c.cancelLabel ?? tr('Отмена')
  confirmState.destructive = c.destructive === true
  confirmState.open = true
  return new Promise<boolean>((resolve) => {
    pending = resolve
  })
}

/** The title and button of a delete question: "Delete" rather than a bare "Confirm". */
export function deleteWording(): { title: string; confirmLabel: string; destructive: true } {
  return { title: tr('Удаление'), confirmLabel: tr('Удалить'), destructive: true }
}

/** The title and button of a permanent delete. */
export function forceDeleteWording(): { title: string; confirmLabel: string; destructive: true } {
  return { title: tr('Удаление навсегда'), confirmLabel: tr('Удалить навсегда'), destructive: true }
}

/** Answers the open question (the dialog's buttons, its cross, Escape). */
export function resolveConfirmDialog(ok: boolean): void {
  confirmState.open = false
  const resolve = pending
  pending = null
  resolve?.(ok)
}

export function useConfirm(): { confirm: typeof confirmDialog } {
  return { confirm: confirmDialog }
}

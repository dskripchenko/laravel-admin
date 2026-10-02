/**
 * adminToast — the facade over useToast() from @dskripchenko/ui, for the
 * admin's flows.
 *
 * @dskripchenko/ui/composables/useToast keeps its toasts in a module
 * singleton, one stack for the whole application. We wrap success, error, info
 * and warning into plain functions with sensible durations, and use
 * AdminClient's ApiError to pull a readable message out.
 */
import { useToast } from '@dskripchenko/ui'
import { trSafe } from './i18n'

interface Options {
  title?: string
  /** The duration in milliseconds; 0 means it never closes by itself. */
  duration?: number
}

function shorthand(message: string, opts?: Options) {
  return { message, ...(opts ?? {}) }
}

/** Until when error toasts are held back; see suppressErrorToasts(). */
let errorsSuppressedUntil = 0

/**
 * Holds error toasts back for a moment. Called after a refusal that was
 * already explained to the user by its own toast (demo mode, the 2FA
 * requirement), so that the caller's generic "could not save" does not pile
 * on top of it.
 */
export function suppressErrorToasts(ms = 1500): void {
  errorsSuppressedUntil = Date.now() + ms
}

export const adminToast = {
  success(message: string, opts?: Options): void {
    useToast().success(shorthand(message, opts))
  },
  error(message: string, opts?: Options): void {
    if (Date.now() < errorsSuppressedUntil) return
    useToast().error(shorthand(message, { duration: 6000, ...(opts ?? {}) }))
  },
  warning(message: string, opts?: Options): void {
    useToast().warning(shorthand(message, opts))
  },
  info(message: string, opts?: Options): void {
    useToast().info(shorthand(message, opts))
  },
}

/**
 * fromError pulls a message out of an ApiError, an Error or anything else and
 * pushes a toast.
 */
export function toastError(err: unknown, fallback = trSafe('Произошла ошибка')): void {
  const msg =
    err instanceof Error
      ? err.message || fallback
      : typeof err === 'string'
        ? err
        : fallback
  adminToast.error(msg)
}

/** One entry of a screen method's `alerts` — see the ScreenAlert schema. */
export interface ServerAlert {
  type?: string
  /** Aliases of `type` that people write for a toast. */
  level?: string
  variant?: string
  message?: string
  title?: string
  duration_ms?: number
}

export type ToastLevel = 'success' | 'info' | 'warning' | 'error'

/**
 * Maps a server alert's `type` onto a toast level. The schema names
 * info|success|warning|danger; `error` and `warn` are accepted as the obvious
 * aliases, and anything unknown falls back to info.
 */
export function alertLevel(type: string | undefined): ToastLevel {
  switch ((type ?? '').toLowerCase()) {
    case 'success':
    case 'ok':
      return 'success'
    case 'warning':
    case 'warn':
      return 'warning'
    case 'danger':
    case 'error':
    case 'critical':
      return 'error'
    default:
      return 'info'
  }
}

/**
 * Shows a server response's `alerts` as toasts. An alert whose text repeats
 * `skipMessage` — the response's `message`, already drawn inline by the page —
 * is left out, so the same sentence never appears twice. Returns how many
 * toasts were shown.
 */
export function toastAlerts(alerts: unknown, skipMessage?: string | null): number {
  if (!Array.isArray(alerts)) return 0
  let shown = 0
  for (const raw of alerts as unknown[]) {
    if (raw === null || typeof raw !== 'object') continue
    const alert = raw as ServerAlert
    const message = typeof alert.message === 'string' ? alert.message.trim() : ''
    if (message === '' || (skipMessage && message === skipMessage.trim())) continue
    const opts: Options = {}
    if (typeof alert.title === 'string' && alert.title !== '') opts.title = alert.title
    if (typeof alert.duration_ms === 'number' && alert.duration_ms >= 0) opts.duration = alert.duration_ms
    adminToast[alertLevel(alert.type ?? alert.level ?? alert.variant)](message, opts)
    shown++
  }
  return shown
}

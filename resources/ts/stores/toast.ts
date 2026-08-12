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

export const adminToast = {
  success(message: string, opts?: Options): void {
    useToast().success(shorthand(message, opts))
  },
  error(message: string, opts?: Options): void {
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

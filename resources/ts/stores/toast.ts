/**
 * adminToast — фасад над useToast() из @dskripchenko/ui для admin-flows.
 *
 * Компонент @dskripchenko/ui/composables/useToast хранит массив toast'ов
 * в module-singleton'е (общий стек на всё приложение). Мы оборачиваем
 * методы success/error/info/warning в простые функции с дефолтными
 * длительностями + используется AdminClient ApiError для извлечения
 * понятных сообщений.
 */
import { useToast } from '@dskripchenko/ui'

interface Options {
  title?: string
  /** Длительность в ms (0 = не закрывать автоматически). */
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
 * fromError — извлекает message из ApiError / Error / unknown и пушит toast.
 */
export function toastError(err: unknown, fallback = 'Произошла ошибка'): void {
  const msg =
    err instanceof Error
      ? err.message || fallback
      : typeof err === 'string'
        ? err
        : fallback
  adminToast.error(msg)
}

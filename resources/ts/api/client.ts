/**
 * The axios client of the admin API.
 *
 * What it does:
 *   - takes its baseURL from bootstrap.apiUrl, unless one is passed explicitly.
 *   - adds X-XSRF-TOKEN automatically from the cookie (Laravel's Sanctum/web).
 *   - falls back to X-CSRF-TOKEN from meta[name=csrf-token], injected by Blade.
 *   - sets X-Admin-Locale whenever there is a current locale.
 *   - unwraps the envelope in a response interceptor and throws an ApiError.
 *   - calls the onUnauthenticated callback on a 401, leaving the redirect to
 *     the login to the consumer.
 *   - marks every request with X-Request-Id; an error carries it (and the
 *     time) as `requestId` / `at`, the code the error state shows and the
 *     server writes into its log context.
 */

import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'
import { isSuccess, type ApiEnvelope, type ErrorEnvelope } from './envelope'
import { NetworkError, stampError, toApiError, UnauthenticatedError } from './errors'

export interface ClientOptions {
  baseURL: string
  csrfToken?: string
  locale?: string
  /** Called on a 401; usually a push to /admin/login. */
  onUnauthenticated?: () => void
}

export interface AdminClient {
  /** The raw axios instance, for the odd special case. */
  raw: AxiosInstance
  get<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<T>
  post<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  put<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  patch<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  delete<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<T>
  /** Sends X-Admin-Locale on every later request: the locale the panel renders. */
  setLocale(locale: string): void
  /**
   * Removes X-Admin-Locale, leaving the locale to the server's chain. The
   * panel itself does not call it: its requests always carry the locale it
   * renders.
   */
  clearLocale(): void
}

export function createAdminClient(opts: ClientOptions): AdminClient {
  const instance = axios.create({
    baseURL: opts.baseURL,
    withCredentials: true,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (opts.csrfToken) {
    instance.defaults.headers.common['X-CSRF-TOKEN'] = opts.csrfToken
  }
  if (opts.locale) {
    instance.defaults.headers.common['X-Admin-Locale'] = opts.locale
  }

  // The CSRF token on every request. The XSRF-TOKEN cookie is always current
  // — the browser refreshes it through Set-Cookie when the session is
  // regenerated on login — while the X-CSRF-TOKEN taken from the bootstrap
  // goes stale. Laravel's tokensMatch() PREFERS X-CSRF-TOKEN over the cookie,
  // so when a fresh cookie is there we REMOVE the static header; otherwise the
  // stale token gives a 419 on setTheme, setLocale and any POST after a
  // client-side login without a reload.
  instance.interceptors.request.use((config) => {
    if (!config.headers.has('X-Request-Id')) config.headers.set('X-Request-Id', newRequestId())
    const xsrf = readCookie('XSRF-TOKEN')
    if (xsrf) {
      config.headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf))
      config.headers.delete('X-CSRF-TOKEN')
    }
    return config
  })

  // The response interceptor: unwrap the envelope, throw the right error.
  instance.interceptors.response.use(
    (response) => {
      const env = response.data as ApiEnvelope
      if (env && typeof env === 'object' && 'success' in env) {
        if (isSuccess(env)) {
          // The payload replaces data, so the callers get the payload alone.
          response.data = env.payload
          return response
        }
        // success: false is thrown as an ApiError
        throw stampError(toApiError(response.status, env.payload), requestIdOf(response.config?.headers))
      }
      // Not an envelope — a binary stream, say — so it passes through untouched.
      return response
    },
    (error: AxiosError<ErrorEnvelope>) => {
      if (error.response) {
        const status = error.response.status
        const payload =
          error.response.data?.payload ?? {
            errorKey: 'unknown',
            message: error.message,
            // No envelope came back (a proxy's 502, a bare 500): the message
            // is axios's own "Request failed…", not the server's reason.
            transport: true,
          }
        const apiError = stampError(toApiError(status, payload), requestIdOf(error.config?.headers))
        if (apiError instanceof UnauthenticatedError) {
          opts.onUnauthenticated?.()
        }
        return Promise.reject(apiError)
      }
      // network failure / timeout
      return Promise.reject(stampError(new NetworkError(error.message), requestIdOf(error.config?.headers)))
    },
  )

  const wrap = <T>(method: 'get' | 'delete', url: string, config?: AxiosRequestConfig): Promise<T> =>
    instance[method]<unknown, { data: T }>(url, config).then((r) => r.data)

  const wrapBody = <T>(
    method: 'post' | 'put' | 'patch',
    url: string,
    data?: unknown,
    config?: AxiosRequestConfig,
  ): Promise<T> => instance[method]<unknown, { data: T }>(url, data, config).then((r) => r.data)

  return {
    raw: instance,
    get: <T>(url: string, config?: AxiosRequestConfig) => wrap<T>('get', url, config),
    delete: <T>(url: string, config?: AxiosRequestConfig) => wrap<T>('delete', url, config),
    post: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
      wrapBody<T>('post', url, data, config),
    put: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
      wrapBody<T>('put', url, data, config),
    patch: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
      wrapBody<T>('patch', url, data, config),
    setLocale: (locale: string): void => {
      instance.defaults.headers.common['X-Admin-Locale'] = locale
    },
    clearLocale: (): void => {
      delete instance.defaults.headers.common['X-Admin-Locale']
    },
  }
}

/**
 * A short code of one request: `7f3a-91c2`. Short enough to read out over the
 * phone, random enough to find the request in the server's log.
 */
export function newRequestId(): string {
  const hex = (n: number): string => {
    let out = ''
    for (let i = 0; i < n; i++) out += Math.floor(Math.random() * 16).toString(16)
    return out
  }
  return `${hex(4)}-${hex(4)}`
}

function requestIdOf(headers: unknown): string | null {
  if (!headers || typeof headers !== 'object') return null
  const h = headers as { get?: (k: string) => unknown } & Record<string, unknown>
  const v = typeof h.get === 'function' ? h.get('X-Request-Id') : h['X-Request-Id']
  return typeof v === 'string' && v !== '' ? v : null
}

/** Reads a cookie from document.cookie, returning null when there is none. */
function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp('(^|; )' + escapeRegex(name) + '=([^;]*)'))
  return match ? match[2] : null
}

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

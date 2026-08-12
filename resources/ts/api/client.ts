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
 */

import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'
import { isSuccess, type ApiEnvelope, type ErrorEnvelope } from './envelope'
import { NetworkError, toApiError, UnauthenticatedError } from './errors'

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
  setLocale(locale: string): void
  /** Removes the pinned X-Admin-Locale, leaving the locale to the server. */
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
        throw toApiError(response.status, env.payload)
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
          }
        const apiError = toApiError(status, payload)
        if (apiError instanceof UnauthenticatedError) {
          opts.onUnauthenticated?.()
        }
        return Promise.reject(apiError)
      }
      // network failure / timeout
      return Promise.reject(new NetworkError(error.message))
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

/** Reads a cookie from document.cookie, returning null when there is none. */
function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp('(^|; )' + escapeRegex(name) + '=([^;]*)'))
  return match ? match[2] : null
}

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

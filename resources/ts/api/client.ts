/**
 * Axios-клиент для admin API.
 *
 * Особенности:
 *   - baseURL берётся из bootstrap.apiUrl (либо передаётся явно).
 *   - X-XSRF-TOKEN добавляется автоматически из cookie (Laravel Sanctum/web).
 *   - X-CSRF-TOKEN — fallback из meta[name=csrf-token] (Blade-injection).
 *   - X-Admin-Locale выставляется при наличии текущей локали.
 *   - Response interceptor разворачивает envelope и бросает ApiError.
 *   - 401 → onUnauthenticated callback (редирект на login на стороне consumer'а).
 */

import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'
import { isSuccess, type ApiEnvelope, type ErrorEnvelope } from './envelope'
import { NetworkError, toApiError, UnauthenticatedError } from './errors'

export interface ClientOptions {
  baseURL: string
  csrfToken?: string
  locale?: string
  /** Вызывается при 401. Обычно — push на /admin/login. */
  onUnauthenticated?: () => void
}

export interface AdminClient {
  /** Низкоуровневый axios — для специфичных случаев. */
  raw: AxiosInstance
  get<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<T>
  post<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  put<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  patch<T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T>
  delete<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<T>
  setLocale(locale: string): void
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

  // CSRF на каждый запрос. XSRF-TOKEN cookie всегда актуален (браузер
  // обновляет его через Set-Cookie при регенерации сессии на логине);
  // bootstrap-снятый X-CSRF-TOKEN — устаревает. Laravel в tokensMatch()
  // ПРЕДПОЧИТАЕТ X-CSRF-TOKEN cookie'у, поэтому при наличии свежего
  // cookie статичный заголовок СНИМАЕМ — иначе стухший токен даёт 419
  // (setTheme/setLocale и любой POST после client-side логина без reload).
  instance.interceptors.request.use((config) => {
    const xsrf = readCookie('XSRF-TOKEN')
    if (xsrf) {
      config.headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf))
      config.headers.delete('X-CSRF-TOKEN')
    }
    return config
  })

  // Response interceptor — разворачиваем envelope, бросаем правильную ошибку.
  instance.interceptors.response.use(
    (response) => {
      const env = response.data as ApiEnvelope
      if (env && typeof env === 'object' && 'success' in env) {
        if (isSuccess(env)) {
          // payload подменяет data — теперь callers получают чистый payload.
          response.data = env.payload
          return response
        }
        // success: false → throw как ApiError
        throw toApiError(response.status, env.payload)
      }
      // Не envelope (например, бинарный stream) — пропускаем как есть.
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
  }
}

/** Читает cookie из document.cookie. Returns null если нет. */
function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp('(^|; )' + escapeRegex(name) + '=([^;]*)'))
  return match ? match[2] : null
}

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

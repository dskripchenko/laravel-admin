/**
 * Контракт ответов laravel-api: каждое тело — `{success, payload}`.
 *
 * Success: `{success: true, payload: {...}}`
 * Error:   `{success: false, payload: {errorKey, message, ...}}`
 *
 * Helper'ы здесь — для type-narrowing'а в потребителях.
 */

export interface SuccessEnvelope<T = unknown> {
  success: true
  payload: T
}

export interface ErrorEnvelope {
  success: false
  payload: {
    errorKey: string
    message: string
    messages?: Record<string, string[]>
    [key: string]: unknown
  }
}

export type ApiEnvelope<T = unknown> = SuccessEnvelope<T> | ErrorEnvelope

export function isSuccess<T>(env: ApiEnvelope<T>): env is SuccessEnvelope<T> {
  return env.success === true
}

export function isError(env: ApiEnvelope): env is ErrorEnvelope {
  return env.success === false
}

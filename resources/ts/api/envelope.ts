/**
 * The response contract of laravel-api: every body is `{success, payload}`.
 *
 * Success: `{success: true, payload: {...}}`
 * Error:   `{success: false, payload: {errorKey, message, ...}}`
 *
 * The helpers here exist to narrow the types on the consumer's side.
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
    /** The per-field errors: `errors` is laravel-api's shape, `messages` is the admin's. */
    messages?: Record<string, string[]>
    errors?: Record<string, string[]>
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

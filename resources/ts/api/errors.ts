/**
 * The admin API's own error classes.
 *
 * Every API error is thrown as an ApiError or one of its subclasses, so a
 * consumer can use `instanceof ValidationError` to handle a particular case.
 */

import type { ErrorEnvelope } from './envelope'

export class ApiError extends Error {
  public readonly status: number
  public readonly errorKey: string
  public readonly payload: ErrorEnvelope['payload']

  constructor(status: number, payload: ErrorEnvelope['payload']) {
    super(payload.message ?? `API error ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.errorKey = payload.errorKey ?? 'unknown'
    this.payload = payload
  }
}

export class UnauthenticatedError extends ApiError {
  constructor(payload: ErrorEnvelope['payload']) {
    super(401, payload)
    this.name = 'UnauthenticatedError'
  }
}

export class ForbiddenError extends ApiError {
  constructor(payload: ErrorEnvelope['payload']) {
    super(403, payload)
    this.name = 'ForbiddenError'
  }
}

export class NotFoundError extends ApiError {
  constructor(payload: ErrorEnvelope['payload']) {
    super(404, payload)
    this.name = 'NotFoundError'
  }
}

export class ValidationError extends ApiError {
  /** Field-keyed map of error messages. */
  public readonly fields: Record<string, string[]>

  constructor(payload: ErrorEnvelope['payload']) {
    super(422, payload)
    this.name = 'ValidationError'
    // The envelope comes in two shapes: the admin sends `messages`, while
    // laravel-api's default ValidationException handler sends `errors`.
    // Reading one of them means silently having no field errors when the other
    // arrives: one presses "Save" and the form answers with nothing at all.
    this.fields = payload.messages ?? payload.errors ?? {}
  }

  /** The field's first message — handy for a toast. */
  firstFieldMessage(): string | null {
    for (const messages of Object.values(this.fields)) {
      if (messages.length > 0) {
        return messages[0]
      }
    }
    return null
  }
}

/**
 * A network failure — the server was never reached. It is a class of its own
 * so that the UI can say "No connection" rather than "500 Server Error".
 */
export class NetworkError extends Error {
  constructor(message = 'Network error') {
    super(message)
    this.name = 'NetworkError'
  }
}

/**
 * Turns an HTTP status and an envelope into the right ApiError subclass.
 */
export function toApiError(status: number, payload: ErrorEnvelope['payload']): ApiError {
  switch (status) {
    case 401: return new UnauthenticatedError(payload)
    case 403: return new ForbiddenError(payload)
    case 404: return new NotFoundError(payload)
    case 422: return new ValidationError(payload)
    default:  return new ApiError(status, payload)
  }
}

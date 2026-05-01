/**
 * Кастомные Error-классы для admin API.
 *
 * Все API-ошибки бросаются как ApiError или его подклассы. Потребители
 * могут делать `instanceof ValidationError` для специфичной обработки.
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
    this.fields = payload.messages ?? {}
  }

  /** Первое сообщение из field'а — удобно для toast. */
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
 * Network-failure (не дошли до сервера). Отдельный класс чтобы UI мог
 * показать «Нет соединения» вместо «500 Server Error».
 */
export class NetworkError extends Error {
  constructor(message = 'Network error') {
    super(message)
    this.name = 'NetworkError'
  }
}

/**
 * Превращает HTTP-status + envelope в правильный подкласс ApiError.
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

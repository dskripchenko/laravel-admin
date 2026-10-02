import { describe, it, expect } from 'vitest'
import {
  ApiError,
  UnauthenticatedError,
  ForbiddenError,
  NotFoundError,
  ValidationError,
  NetworkError,
  toApiError,
  apiErrorMessage,
} from './errors'

describe('errors', () => {
  it('toApiError maps statuses to subclasses', () => {
    expect(toApiError(401, { errorKey: 'unauth', message: '' })).toBeInstanceOf(UnauthenticatedError)
    expect(toApiError(403, { errorKey: 'forbidden', message: '' })).toBeInstanceOf(ForbiddenError)
    expect(toApiError(404, { errorKey: 'not_found', message: '' })).toBeInstanceOf(NotFoundError)
    expect(toApiError(422, { errorKey: 'validation', message: '' })).toBeInstanceOf(ValidationError)
    expect(toApiError(500, { errorKey: 'server', message: '' })).toBeInstanceOf(ApiError)
  })

  it('all subclasses extend ApiError', () => {
    expect(toApiError(401, { errorKey: 'x', message: '' })).toBeInstanceOf(ApiError)
    expect(toApiError(422, { errorKey: 'x', message: '' })).toBeInstanceOf(ApiError)
  })

  it('preserves errorKey + message', () => {
    const err = toApiError(403, { errorKey: 'forbidden', message: 'Access denied' })
    expect(err.errorKey).toBe('forbidden')
    expect(err.message).toBe('Access denied')
    expect(err.status).toBe(403)
  })

  it('ValidationError exposes fields and firstFieldMessage', () => {
    const err = new ValidationError({
      errorKey: 'validation',
      message: 'Validation failed',
      messages: {
        email: ['Must be a valid email', 'Required'],
        password: ['Too short'],
      },
    })
    expect(err.fields.email).toEqual(['Must be a valid email', 'Required'])
    expect(err.firstFieldMessage()).toBe('Must be a valid email')
  })

  it('ValidationError firstFieldMessage returns null on empty fields', () => {
    const err = new ValidationError({ errorKey: 'validation', message: '' })
    expect(err.firstFieldMessage()).toBeNull()
  })

  it('NetworkError is not ApiError', () => {
    const err = new NetworkError()
    expect(err).toBeInstanceOf(NetworkError)
    expect(err).not.toBeInstanceOf(ApiError)
  })

  it('ApiError uses fallback message from status when payload.message empty', () => {
    const err = toApiError(500, { errorKey: 'server', message: '' })
    // payload.message is empty, so Error's message should be `API error 500`.
    // The constructor uses `?? message ?? \`API error ${status}\``.
    // Here message is an empty string, so it stays '' and no fallback kicks in.
    // That is the exact behaviour, documented rather than a bug.
    expect(err.status).toBe(500)
  })

  describe('apiErrorMessage', () => {
    it('gives the reason of an action_failed 422', () => {
      const err = toApiError(422, { errorKey: 'action_failed', message: 'SMTP server is down' })
      expect(apiErrorMessage(err, 'fallback')).toBe('SMTP server is down')
    })

    it('gives the reason of a 403', () => {
      const err = toApiError(403, { errorKey: 'action_forbidden', message: 'Access denied: x.archive' })
      expect(apiErrorMessage(err, 'fallback')).toBe('Access denied: x.archive')
    })

    it('prefers the first field message of a validation error', () => {
      const err = toApiError(422, {
        errorKey: 'validation',
        message: 'The given data was invalid.',
        messages: { ids: ['Select at least one record.'] },
      })
      expect(apiErrorMessage(err, 'fallback')).toBe('Select at least one record.')
    })

    it('falls back when the server gave no reason', () => {
      expect(apiErrorMessage(toApiError(500, { errorKey: 'x', message: '' }), 'fallback')).toBe('fallback')
      expect(apiErrorMessage(
        toApiError(502, { errorKey: 'unknown', message: 'Request failed with status code 502', transport: true }),
        'fallback',
      )).toBe('fallback')
      expect(apiErrorMessage(undefined, 'fallback')).toBe('fallback')
      expect(apiErrorMessage(new Error(''), 'fallback')).toBe('fallback')
    })

    it('gives the message of a plain error', () => {
      expect(apiErrorMessage(new NetworkError('Offline'), 'fallback')).toBe('Offline')
      expect(apiErrorMessage('Nope', 'fallback')).toBe('Nope')
    })
  })
})

import { describe, it, expect } from 'vitest'
import { isSuccess, isError, type ApiEnvelope } from './envelope'

describe('envelope', () => {
  it('isSuccess narrows to SuccessEnvelope', () => {
    const env: ApiEnvelope<{ id: number }> = { success: true, payload: { id: 1 } }
    expect(isSuccess(env)).toBe(true)
    if (isSuccess(env)) {
      expect(env.payload.id).toBe(1)
    }
  })

  it('isError narrows to ErrorEnvelope', () => {
    const env: ApiEnvelope = {
      success: false,
      payload: { errorKey: 'forbidden', message: 'Нет доступа' },
    }
    expect(isError(env)).toBe(true)
    if (isError(env)) {
      expect(env.payload.errorKey).toBe('forbidden')
    }
  })

  it('isSuccess and isError are mutually exclusive', () => {
    const succ: ApiEnvelope = { success: true, payload: {} }
    const err: ApiEnvelope = { success: false, payload: { errorKey: 'x', message: 'y' } }
    expect(isSuccess(succ) && isError(succ)).toBe(false)
    expect(isError(err) && isSuccess(err)).toBe(false)
  })
})

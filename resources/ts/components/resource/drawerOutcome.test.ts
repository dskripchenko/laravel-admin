import { describe, it, expect } from 'vitest'
import { actionKeepsRecord } from './drawerOutcome'
import type { AdminAction } from '../../composables/useActionRunner'

const action = (attributes: Record<string, unknown> = {}): AdminAction => ({
  name: 'a', label: 'A', type: 'button', icon: null, confirm: null,
  primary: false, destructive: false, position: ['row'], attributes, items: [],
})

describe('actionKeepsRecord', () => {
  it('follows the action\'s keepsRecord() when the answer says nothing', () => {
    expect(actionKeepsRecord(action(), { affected: 1 })).toBe(false)
    expect(actionKeepsRecord(action({ keepsRecord: true }), { affected: 1 })).toBe(true)
    expect(actionKeepsRecord(action({ keepsRecord: false }), null)).toBe(false)
    expect(actionKeepsRecord(null, undefined)).toBe(false)
  })

  it('takes keep_record of the answer over the flag', () => {
    expect(actionKeepsRecord(action(), { keep_record: true })).toBe(true)
    expect(actionKeepsRecord(action({ keepsRecord: true }), { keep_record: false })).toBe(false)
    // Not a boolean: as if absent.
    expect(actionKeepsRecord(action({ keepsRecord: true }), { keep_record: 'no' })).toBe(true)
  })
})

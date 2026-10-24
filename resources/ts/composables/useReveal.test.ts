import { describe, expect, it } from 'vitest'
import { actionReveal, closeReveal, revealOnce, revealState } from './useReveal'

describe('useReveal', () => {
  it('shows the value until closed, then forgets it', async () => {
    const done = revealOnce({ value: 'tok-1', title: 'Issue a token', message: 'Copy it now', label: 'Token' })
    expect(revealState).toMatchObject({ open: true, value: 'tok-1', title: 'Issue a token', message: 'Copy it now', label: 'Token' })

    closeReveal()
    await done
    expect(revealState.open).toBe(false)
    expect(revealState.value).toBe('')
  })

  it('a second value replaces an unclosed one', async () => {
    const first = revealOnce({ value: 'a' })
    revealOnce({ value: 'b' })
    await first
    expect(revealState.value).toBe('b')
    closeReveal()
  })

  it('reads reveal from an action answer', () => {
    expect(actionReveal({ message: 'x', reveal: { value: 'v', label: 'L' } })).toEqual({ value: 'v', label: 'L' })
    expect(actionReveal({ reveal: { value: 'v' } })).toEqual({ value: 'v', label: null })
    expect(actionReveal({ message: 'x' })).toBeNull()
    expect(actionReveal({ reveal: { value: '' } })).toBeNull()
    expect(actionReveal(null)).toBeNull()
  })
})

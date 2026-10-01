import { describe, it, expect, vi } from 'vitest'
import { ref } from 'vue'
import { createScreenContext } from './screenContext'

describe('screen context', () => {
  it('opens an overlay for attributes.opens without calling a method', async () => {
    const runMethod = vi.fn(async () => true)
    const ctx = createScreenContext({ running: ref(false), runMethod })
    expect(await ctx.dispatch({ attributes: { opens: 'm', method: 'save' } })).toBe(true)
    expect(ctx.isOpen('m')).toBe(true)
    expect(runMethod).not.toHaveBeenCalled()
    ctx.close('m')
    expect(ctx.isOpen('m')).toBe(false)
  })

  it('calls the method and reports its outcome', async () => {
    const runMethod = vi.fn(async () => false)
    const ctx = createScreenContext({ running: ref(false), runMethod })
    expect(await ctx.dispatch({ attributes: { method: 'save' } })).toBe(false)
    expect(runMethod).toHaveBeenCalledWith('save')
  })

  it('stops at a declined confirmation', async () => {
    const runMethod = vi.fn(async () => true)
    const ctx = createScreenContext({ running: ref(false), runMethod, confirm: () => false })
    expect(await ctx.dispatch({ confirm: { message: 'Sure?' }, attributes: { method: 'drop' } })).toBe(false)
    expect(runMethod).not.toHaveBeenCalled()
  })
})

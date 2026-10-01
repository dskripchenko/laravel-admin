import { describe, it, expect, beforeEach } from 'vitest'
import { useToast } from '@dskripchenko/ui'
import { alertLevel, toastAlerts } from './toast'

describe('alertLevel', () => {
  it('maps the schema types and their aliases onto toast levels', () => {
    expect(alertLevel('success')).toBe('success')
    expect(alertLevel('info')).toBe('info')
    expect(alertLevel('warning')).toBe('warning')
    expect(alertLevel('warn')).toBe('warning')
    expect(alertLevel('danger')).toBe('error')
    expect(alertLevel('error')).toBe('error')
    expect(alertLevel('ERROR')).toBe('error')
  })

  it('falls back to info for anything unknown or missing', () => {
    expect(alertLevel('primary')).toBe('info')
    expect(alertLevel(undefined)).toBe('info')
  })
})

describe('toastAlerts', () => {
  beforeEach(() => {
    useToast().clear()
  })

  it('pushes one toast per alert with the mapped variant', () => {
    const shown = toastAlerts([
      { type: 'success', message: 'Saved' },
      { type: 'info', message: 'FYI' },
      { type: 'warning', message: 'Careful' },
      { type: 'danger', message: 'Broken' },
    ])

    expect(shown).toBe(4)
    const toasts = useToast().toasts.value
    expect(toasts.map((t) => [t.variant, t.message])).toEqual([
      ['success', 'Saved'],
      ['info', 'FYI'],
      ['warning', 'Careful'],
      ['danger', 'Broken'],
    ])
  })

  it('passes title and duration_ms through', () => {
    toastAlerts([{ type: 'info', message: 'Hi', title: 'Note', duration_ms: 0 }])

    const [toast] = useToast().toasts.value
    expect(toast.title).toBe('Note')
    expect(toast.duration).toBe(0)
  })

  it('skips the alert that repeats the inline message, and malformed entries', () => {
    const shown = toastAlerts(
      [
        { type: 'success', message: 'Sent' },
        { type: 'success', message: '' },
        null,
        'oops',
        { type: 'info', message: 'Queued for delivery' },
      ],
      'Sent',
    )

    expect(shown).toBe(1)
    expect(useToast().toasts.value.map((t) => t.message)).toEqual(['Queued for delivery'])
  })

  it('ignores a missing or non-array payload', () => {
    expect(toastAlerts(undefined)).toBe(0)
    expect(toastAlerts({ type: 'info', message: 'x' })).toBe(0)
    expect(useToast().toasts.value).toHaveLength(0)
  })
})

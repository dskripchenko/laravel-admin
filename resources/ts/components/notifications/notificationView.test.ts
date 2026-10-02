import { describe, it, expect } from 'vitest'
import { AlertTriangle, Bell, CheckCircle, MessageSquare, XCircle } from 'lucide-vue-next'
import { notificationBody, notificationIcon, notificationTitle, notificationTone } from './notificationView'
import { resolveIcon } from '../shell/iconRegistry'
import type { NotificationItem } from '../../stores/notifications'

const item = (data: Record<string, unknown>, type = 'App\\Notifications\\Custom'): NotificationItem => ({
  id: '1', type, data, read_at: null, created_at: null,
}) as NotificationItem

describe('notificationView', () => {
  it('reads AdminNotification title and body, then the usual keys of a custom one', () => {
    expect(notificationTitle(item({ title: 'T' }), '—')).toBe('T')
    expect(notificationTitle(item({ subject: 'S' }), '—')).toBe('S')
    expect(notificationTitle(item({}), '—')).toBe('—')
    expect(notificationBody(item({ body: 'B', message: 'M' }))).toBe('B')
    expect(notificationBody(item({ message: 'M' }))).toBe('M')
    expect(notificationBody(item({}))).toBe('')
  })

  it('takes the tone from the level, error included, else guesses it from the type', () => {
    expect(notificationTone(item({ level: 'error' }))).toBe('danger')
    expect(notificationTone(item({ level: 'warning' }))).toBe('warning')
    expect(notificationTone(item({ level: 'info' }, 'App\\ImportFinished'))).toBe('info')
    expect(notificationTone(item({}, 'App\\ImportFinished'))).toBe('success')
    expect(notificationTone(item({}))).toBe('neutral')
  })

  it('uses the named icon, else one for the kind or the tone', () => {
    expect(notificationIcon(item({ icon: 'bell', level: 'success' }))).toBe(resolveIcon('bell'))
    expect(notificationIcon(item({ level: 'success' }))).toBe(CheckCircle)
    expect(notificationIcon(item({ level: 'warning' }))).toBe(AlertTriangle)
    expect(notificationIcon(item({ level: 'error' }))).toBe(XCircle)
    expect(notificationIcon(item({}, 'App\\NewComment'))).toBe(MessageSquare)
    expect(notificationIcon(item({}))).toBe(Bell)
  })
})

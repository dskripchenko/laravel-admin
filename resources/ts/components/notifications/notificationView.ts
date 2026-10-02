/**
 * How a stored notification reads: its title, body, tone and icon.
 *
 * AdminNotification stores {title, body, level, url, icon}; a notification of
 * the host's own may carry `subject`, `message`, `description` or `text`
 * instead, and a type name (its FQCN) is the last hint at its tone.
 */
import type { Component } from 'vue'
import {
  AlertTriangle,
  Bell,
  CheckCircle,
  Info,
  MessageSquare,
  UserPlus,
  XCircle,
} from 'lucide-vue-next'
import type { NotificationItem } from '../../stores/notifications'
import { resolveIcon } from '../shell/iconRegistry'

export type NotificationTone = 'success' | 'info' | 'warning' | 'danger' | 'neutral'

const str = (v: unknown): string | null => (typeof v === 'string' && v !== '' ? v : null)

export function notificationTitle(item: NotificationItem, fallback: string): string {
  return str(item.data.title) ?? str(item.data.subject) ?? fallback
}

export function notificationBody(item: NotificationItem): string {
  const d = item.data
  return str(d.body) ?? str(d.description) ?? str(d.message) ?? str(d.text) ?? ''
}

/** The tone: the level AdminNotification stores, else a guess from the type. */
export function notificationTone(item: NotificationItem): NotificationTone {
  switch (item.data.level) {
    case 'success': return 'success'
    case 'warning': return 'warning'
    case 'error':
    case 'danger': return 'danger'
    case 'info': return 'info'
  }
  const t = (item.type ?? '').toLowerCase()
  if (t.includes('import') || t.includes('success') || t.includes('finished')) return 'success'
  if (t.includes('warning') || t.includes('schedule')) return 'warning'
  if (t.includes('delete') || t.includes('failed') || t.includes('error')) return 'danger'
  if (t.includes('comment') || t.includes('message')) return 'info'
  return 'neutral'
}

const TONE_ICONS: Record<NotificationTone, Component> = {
  success: CheckCircle,
  info: Info,
  warning: AlertTriangle,
  danger: XCircle,
  neutral: Bell,
}

/** The icon: the one the notification names, else one for its kind or tone. */
export function notificationIcon(item: NotificationItem): Component {
  const named = resolveIcon(str(item.data.icon))
  if (named) return named
  const t = (item.type ?? '').toLowerCase()
  if (item.data.level === undefined) {
    if (t.includes('comment') || t.includes('message') || t.includes('mention')) return MessageSquare
    if (t.includes('user') || t.includes('member') || t.includes('role')) return UserPlus
  }
  return TONE_ICONS[notificationTone(item)]
}

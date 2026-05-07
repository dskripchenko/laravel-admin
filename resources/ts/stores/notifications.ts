/**
 * Notifications store: список + unread count + read/delete actions.
 *
 * Bell-badge polling реализуется на уровне UI через setInterval +
 * вызов loadUnread() — store сам не polling'ит, чтобы не дублировать
 * SetInterval'ы между tab'ами.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import type { AdminBootstrap } from '../types/bootstrap'

export interface NotificationItem {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string | null
}

export type NotificationFilter = 'all' | 'unread' | 'read'

export const useNotificationsStore = defineStore('admin-notifications', () => {
  const items = ref<NotificationItem[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const lastFilter = ref<NotificationFilter>('all')
  const meta = ref<{ page: number; per_page: number; total: number; last_page: number } | null>(null)
  /**
   * Slide-in drawer state. Bell-кнопка в топбаре toggle'ит этот флаг,
   * NotificationsDrawer (mounted в AdminApp root) реагирует на него.
   */
  const isOpen = ref<boolean>(false)

  const hasUnread = computed(() => unreadCount.value > 0)

  function openDrawer(): void {
    isOpen.value = true
  }
  function closeDrawer(): void {
    isOpen.value = false
  }
  function toggleDrawer(): void {
    isOpen.value = !isOpen.value
  }

  function hydrate(bootstrap: AdminBootstrap): void {
    unreadCount.value = bootstrap.unread_notifications_count
  }

  async function load(filter: NotificationFilter = 'all', page = 1): Promise<void> {
    loading.value = true
    try {
      const client = getAdminClient()
      const result = await client.get<{
        data: NotificationItem[]
        meta: { page: number; per_page: number; total: number; last_page: number; unread_count: number }
      }>(`/notifications/list?type=${filter}&page=${page}`)
      items.value = result.data
      unreadCount.value = result.meta.unread_count
      meta.value = {
        page: result.meta.page,
        per_page: result.meta.per_page,
        total: result.meta.total,
        last_page: result.meta.last_page,
      }
      lastFilter.value = filter
    } finally {
      loading.value = false
    }
  }

  /**
   * Лёгкий polling-endpoint — только count + последние 20.
   * Для bell-badge.
   */
  async function loadUnread(): Promise<void> {
    const client = getAdminClient()
    const result = await client.get<{ count: number; data: NotificationItem[] }>(
      '/notifications/unread',
    )
    unreadCount.value = result.count
    // Не подменяем items если в данный момент open'нут другой filter.
    if (lastFilter.value === 'unread') {
      items.value = result.data
    }
  }

  async function markAsRead(id: string): Promise<void> {
    const client = getAdminClient()
    await client.post('/notifications/markAsRead', { id })
    // Optimistic update в локальном state.
    const item = items.value.find((n) => n.id === id)
    if (item && item.read_at === null) {
      item.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  async function markAllAsRead(): Promise<void> {
    const client = getAdminClient()
    await client.post('/notifications/markAllAsRead')
    const now = new Date().toISOString()
    for (const item of items.value) {
      if (item.read_at === null) item.read_at = now
    }
    unreadCount.value = 0
  }

  async function destroy(id: string): Promise<void> {
    const client = getAdminClient()
    await client.post('/notifications/destroy', { id })
    const index = items.value.findIndex((n) => n.id === id)
    if (index !== -1) {
      const wasUnread = items.value[index].read_at === null
      items.value.splice(index, 1)
      if (wasUnread) {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    }
  }

  return {
    items,
    unreadCount,
    loading,
    meta,
    lastFilter,
    isOpen,
    hasUnread,
    hydrate,
    load,
    loadUnread,
    markAsRead,
    markAllAsRead,
    destroy,
    openDrawer,
    closeDrawer,
    toggleDrawer,
  }
})

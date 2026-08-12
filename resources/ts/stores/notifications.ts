/**
 * The notifications store: the list, the unread count, and the read and delete
 * actions.
 *
 * The bell badge is polled by the UI, through a setInterval calling
 * loadUnread(); the store does no polling of its own, so as not to multiply
 * intervals across the tabs.
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
   * Whether the sliding drawer is open. The bell in the topbar toggles this
   * flag, and NotificationsDrawer — mounted at AdminApp's root — follows it.
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
   * The light polling endpoint — the count and the latest twenty alone, for
   * the bell's badge.
   */
  async function loadUnread(): Promise<void> {
    const client = getAdminClient()
    const result = await client.get<{ count: number; data: NotificationItem[] }>(
      '/notifications/unread',
    )
    unreadCount.value = result.count
    // The items are not replaced while another filter is open.
    if (lastFilter.value === 'unread') {
      items.value = result.data
    }
  }

  async function markAsRead(id: string): Promise<void> {
    const client = getAdminClient()
    await client.post('/notifications/markAsRead', { id })
    // An optimistic update of the local state.
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

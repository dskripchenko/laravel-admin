<script setup lang="ts">
/**
 * Bell с unread-badge — toggle'ит slide-in drawer NotificationsDrawer.
 * Сам drawer mounted один раз в AdminApp.vue (Teleport to body).
 */
import { computed } from 'vue'
import { Bell } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { useNotificationsStore } from '../../../stores/notifications'

const notifications = useNotificationsStore()

const display = computed(() => {
  const n = notifications.unreadCount
  if (n <= 0) return null
  return n > 99 ? '99+' : String(n)
})

function onClick(): void {
  notifications.toggleDrawer()
}
</script>

<template>
  <button
    type="button"
    class="admin-topbar__icon-btn"
    aria-label="Уведомления"
    aria-haspopup="dialog"
    :aria-expanded="notifications.isOpen"
    @click="onClick"
  >
    <UidIcon :icon="Bell" :size="18" data-icon="bell" />
    <span
      v-if="display !== null"
      class="admin-topbar__bell-badge"
      :title="`Непрочитанных: ${notifications.unreadCount}`"
    >
      {{ display }}
    </span>
  </button>
</template>

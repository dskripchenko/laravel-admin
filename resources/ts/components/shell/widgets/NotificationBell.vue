<script setup lang="ts">
/**
 * Bell с unread-badge. По эталону handoff'а — badge `.admin-topbar__bell-badge`
 * (округлая красная пилюля 14×14 + tabular-nums + max '99+').
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useNotificationsStore } from '../../../stores/notifications'

const notifications = useNotificationsStore()

const display = computed(() => {
  const n = notifications.unreadCount
  if (n <= 0) return null
  return n > 99 ? '99+' : String(n)
})
</script>

<template>
  <RouterLink
    to="/notifications"
    class="admin-topbar__icon-btn"
    aria-label="Уведомления"
  >
    <span class="admin-topbar__icon" data-icon="bell" />
    <span
      v-if="display !== null"
      class="admin-topbar__bell-badge"
      :title="`Непрочитанных: ${notifications.unreadCount}`"
    >
      {{ display }}
    </span>
  </RouterLink>
</template>

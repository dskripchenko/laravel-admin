<script setup lang="ts">
import { useNotificationsStore } from '../../../stores/notifications'

const notifications = useNotificationsStore()
</script>

<template>
  <RouterLink to="/notifications" class="admin-widget admin-notification-bell" aria-label="Уведомления">
    <span class="admin-notification-bell__icon" aria-hidden="true">🔔</span>
    <span
      v-if="notifications.hasUnread"
      class="admin-notification-bell__badge"
      :title="`Непрочитанных: ${notifications.unreadCount}`"
    >
      {{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}
    </span>
  </RouterLink>
</template>

<style>
.admin-notification-bell {
  position: relative;
  text-decoration: none;
}
.admin-notification-bell__badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 9px;
  background: var(--admin-danger, #ef4444);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>

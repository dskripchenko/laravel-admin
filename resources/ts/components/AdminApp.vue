<script setup lang="ts">
/**
 * Root компонент SPA admin'а — рендерит router-view, оборачивая его в
 * AdminShell layout (TopBar + Sidebar + content) для аутентифицированных
 * роутов. Auth-роуты (login, 403, 404) и роуты с meta.fullscreen рендерятся
 * без shell.
 *
 * Используется createAdminApp() как root-component. Host'ы редко
 * переопределяют — обычно достаточно настроить отдельные pages через опции.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AdminShell from './shell/AdminShell.vue'

const route = useRoute()

const useShell = computed<boolean>(() => {
  if (route.meta?.fullscreen === true) return false
  if (route.meta?.kind === 'auth') return false
  if (route.name === 'admin.notFound') return false
  return true
})
</script>

<template>
  <AdminShell v-if="useShell">
    <router-view v-slot="{ Component }">
      <Transition name="admin-page" mode="out-in">
        <component :is="Component" />
      </Transition>
    </router-view>
  </AdminShell>
  <router-view v-else v-slot="{ Component }">
    <Transition name="admin-page" mode="out-in">
      <component :is="Component" />
    </Transition>
  </router-view>
</template>

<style>
/* Плавный page-transition: 120ms fade. Короткий — чтоб не казалось "медленно". */
.admin-page-enter-active,
.admin-page-leave-active {
  transition: opacity 120ms ease-out;
}
.admin-page-enter-from,
.admin-page-leave-to {
  opacity: 0;
}
</style>

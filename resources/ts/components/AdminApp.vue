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
  // Auth-routes (login) рендерятся без shell.
  if (route.meta?.kind === 'auth') return false
  // Catch-all 404 без shell — чтобы пользователь не "застрял" в shell для невалидных URL.
  if (route.name === 'admin.notFound') return false
  return true
})
</script>

<template>
  <AdminShell v-if="useShell">
    <router-view />
  </AdminShell>
  <router-view v-else />
</template>

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
    <div class="admin-page-host">
      <router-view v-slot="{ Component }">
        <Transition name="admin-page">
          <component :is="Component" />
        </Transition>
      </router-view>
    </div>
  </AdminShell>
  <router-view v-else v-slot="{ Component }">
    <Transition name="admin-page">
      <component :is="Component" />
    </Transition>
  </router-view>
</template>

<style>
/*
 * Page-host: relative контейнер чтобы старая и новая страницы могли
 * перекрываться абсолютно во время transition'а. Без этого default
 * mode (in-out) или out-in оставляют пустое место → layout shift,
 * который виден как "дёргание" sidebar/header.
 *
 * Default Transition mode (одновременный enter+leave) + absolute
 * leaving = плавное перекрытие без сдвига контента ниже.
 */
.admin-page-host {
  position: relative;
  min-height: 200px;
}

/* Leaving page absolute-positioned — не двигает layout. */
.admin-page-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
}

.admin-page-enter-active,
.admin-page-leave-active {
  transition: opacity 120ms ease-out;
}

.admin-page-enter-from,
.admin-page-leave-to {
  opacity: 0;
}
</style>

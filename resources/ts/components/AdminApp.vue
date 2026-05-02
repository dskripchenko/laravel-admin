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
import AdminLoadingBar from './AdminLoadingBar.vue'

const route = useRoute()

const useShell = computed<boolean>(() => {
  if (route.meta?.fullscreen === true) return false
  if (route.meta?.kind === 'auth') return false
  if (route.name === 'admin.notFound') return false
  return true
})
</script>

<template>
  <!-- Top loading-bar: показывается пока nav/data не закончили (см. useNavigationStore). -->
  <AdminLoadingBar />

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
 * Page-host: relative контейнер. Старая страница leaving делается absolute
 * (overlay поверх новой), entering в нормальном flow с slide-from-right.
 *
 * Тайминги (по запросу):
 *   - leaving fade-out: 140ms ease-out (быстро уходит).
 *   - entering slide-from-right + fade-in: 320ms cubic-bezier easing,
 *     с 60ms delay чтобы старая успела "пропасть до того как новая
 *     доберётся до позиции".
 *
 * overflow:hidden на host — slide справа не вылезает за viewport.
 */
.admin-page-host {
  position: relative;
  min-height: 200px;
  overflow: hidden;
}

/* Leaving page absolute — не двигает layout, остаётся на месте пока fade'ится. */
.admin-page-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
  transition: opacity 140ms ease-out;
}

.admin-page-leave-to {
  opacity: 0;
}

/* Entering page: slide-in справа + fade-in. Delay 60ms — старая уже почти ушла. */
.admin-page-enter-active {
  transition:
    opacity 320ms cubic-bezier(0.2, 0.8, 0.2, 1) 60ms,
    transform 320ms cubic-bezier(0.2, 0.8, 0.2, 1) 60ms;
}

.admin-page-enter-from {
  opacity: 0;
  transform: translateX(28px);
}

.admin-page-enter-to {
  opacity: 1;
  transform: translateX(0);
}

@media (prefers-reduced-motion: reduce) {
  .admin-page-enter-active,
  .admin-page-leave-active {
    transition: opacity 80ms ease-out;
  }
  .admin-page-enter-from {
    transform: none;
  }
}
</style>

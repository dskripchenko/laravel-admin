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
import { UidToastProvider } from '@dskripchenko/ui'
import AdminShell from './shell/AdminShell.vue'
import AdminLoadingBar from './AdminLoadingBar.vue'
import NotificationsDrawer from './shell/NotificationsDrawer.vue'
import { useManifestStore } from '../stores/manifest'
import { useAuthStore } from '../stores/auth'
import { adminToast } from '../stores/toast'
import { useBrand } from '../composables/useBrand'

const route = useRoute()
const manifest = useManifestStore()
const auth = useAuthStore()
const brand = useBrand()

/**
 * Impersonation state — backend кладёт в bootstrap.user.impersonator
 * объект `{name, email}`, если активный сеанс — impersonation. Frontend
 * отображает баннер сверху shell'а.
 */
const impersonation = computed<{ asName: string } | null>(() => {
  const u = auth.user as Record<string, unknown> | null
  const imp = u?.impersonator as Record<string, unknown> | null | undefined
  if (!imp) return null
  // u.name — кого мы изображаем; imp.name — кто настоящий админ.
  return { asName: String(u?.name ?? '?') }
})

async function exitImpersonation(): Promise<void> {
  try {
    const { getAdminClient } = await import('../stores/registry')
    const client = getAdminClient()
    await client.post('/auth/stopImpersonation')
    window.location.reload()
  } catch {
    adminToast.error('Не удалось выйти из режима импертонации.')
  }
}

const useShell = computed<boolean>(() => {
  if (route.meta?.fullscreen === true) return false
  if (route.meta?.kind === 'auth') return false
  if (route.name === 'admin.notFound') return false
  return true
})

/**
 * При reload deep-link страницы (/admin/r/articles/1/edit) Vue Router
 * сначала матчит против static routes (dynamic resource routes ещё не
 * добавлены — manifest async-load в createAdminApp), и фолбэк-резолв
 * попадает в catch-all `admin.notFound`. Через 50-200ms manifest приходит,
 * createAdminApp re-resolve'ит current route → правильная страница.
 *
 * Чтобы избежать вспышки 404 в этот промежуток — скрываем NotFoundPage
 * пока initial manifest+re-resolve не закончился (manifest.bootResolved).
 * Гейт открывается createAdminApp.loadAndApply() в finally — после того,
 * как router.replace(currentFullPath) уже отработал. Это закрывает окно
 * между "manifest пришёл" и "route переключился", где иначе виден 404.
 */
const suppressNotFound = computed<boolean>(
  () => route.name === 'admin.notFound' && !manifest.bootResolved,
)
</script>

<template>
  <!-- Top loading-bar: показывается пока nav/data не закончили (см. useNavigationStore). -->
  <AdminLoadingBar />

  <AdminShell
    v-if="useShell"
    :impersonation="impersonation"
    :brand="brand"
    @exit-impersonation="exitImpersonation"
  >
    <div class="admin-page-host">
      <router-view v-slot="{ Component }">
        <Transition name="admin-page">
          <component v-if="!suppressNotFound" :is="Component" />
          <div v-else class="admin-page-host__suspended" aria-busy="true" />
        </Transition>
      </router-view>
    </div>
  </AdminShell>
  <router-view v-else v-slot="{ Component }">
    <Transition name="admin-page">
      <component v-if="!suppressNotFound" :is="Component" />
      <div v-else class="admin-page-host__suspended" aria-busy="true" />
    </Transition>
  </router-view>

  <!-- Slide-in drawer уведомлений; mounted один раз, открывается через
       notificationsStore.toggleDrawer() (см. NotificationBell). -->
  <NotificationsDrawer />

  <!-- Toast-stack для админских уведомлений (success/error/info).
       useToast() из @dskripchenko/ui — push'ит сообщения в общий store. -->
  <UidToastProvider />
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

/* Заглушка-noop пока manifest не resolved — заменяет потенциальный flash
   NotFoundPage при reload deep-link. Top loading-bar показывает что
   что-то происходит. */
.admin-page-host__suspended {
  min-height: 320px;
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

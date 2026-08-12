<script setup lang="ts">
/**
 * The admin SPA's root component: it renders router-view, wrapping it into the
 * AdminShell layout (topbar + sidebar + content) for authenticated routes. The
 * auth routes (login, 403, 404) and anything with meta.fullscreen are rendered
 * without the shell.
 *
 * createAdminApp() uses it as the root component. Hosts rarely override it —
 * configuring individual pages through the options is usually enough.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { UidToastProvider } from '@dskripchenko/ui'
import AdminShell from './shell/AdminShell.vue'
import AdminBootSkeleton from './shell/AdminBootSkeleton.vue'
import AdminLoadingBar from './AdminLoadingBar.vue'
import NotificationsDrawer from './shell/NotificationsDrawer.vue'
import { useAuthStore } from '../stores/auth'
import { adminToast } from '../stores/toast'
import { useBrand } from '../composables/useBrand'
import { useAppReady } from '../composables/useAppReady'
import { useShellVisibility } from '../composables/useShellVisibility'
import { provideLocale, ru as uidRu, en as uidEn } from '@dskripchenko/ui'
import { useLocaleStore } from '../stores/locale'
import { trSafe as tr } from '../stores/i18n'

const route = useRoute()
const auth = useAuthStore()
const brand = useBrand()

// The UI kit's locale follows the panel's, so that the built-in strings of
// the @dskripchenko/ui primitives ("Select…" and the rest) follow it too.
const localeStore = useLocaleStore()
provideLocale(computed(() => (localeStore.current === 'en' ? uidEn : uidRu)))

/**
 * The impersonation state: when the active session is an impersonation, the
 * backend puts a `{name, email}` object into bootstrap.user.impersonator, and
 * the frontend shows a banner above the shell.
 */
const impersonation = computed<{ asName: string } | null>(() => {
  const u = auth.user as Record<string, unknown> | null
  const imp = u?.impersonator as Record<string, unknown> | null | undefined
  if (!imp) return null
  // u.name is whom we are impersonating, imp.name is the real administrator.
  return { asName: String(u?.name ?? '?') }
})

async function exitImpersonation(): Promise<void> {
  try {
    const { getAdminClient } = await import('../stores/registry')
    const client = getAdminClient()
    await client.post('/auth/stopImpersonation')
    window.location.reload()
  } catch {
    adminToast.error(tr('Не удалось выйти из режима импертонации.'))
  }
}

const appReady = useAppReady()

const { useShell } = useShellVisibility(route, appReady)

/**
 * Until the shell is assembled we do not render the page at all — a skeleton
 * stands in its place.
 *
 * The gate used to cover only the 404 flash: a deep link resolves into the
 * catch-all while the manifest has not brought the dynamic routes yet. But
 * that very same hole also produced a flash of the wrong screen: without the
 * manifest HomePage knows nothing of the host's dashboards and draws the
 * "register a DashboardScreen" placeholder, which the real dashboard replaces
 * a quarter of a second later. Measured on the stand: the shell at 236 ms, the
 * real content at 510 ms.
 *
 * So the gate is a common one (useAppReady) and holds back any page until the
 * manifest and the menu are ready — they load in parallel and arrive almost
 * together.
 */
const showPage = appReady

/**
 * The first appearance of a page replaces the skeleton, it is not a move
 * between screens: a skeleton sliding out sideways would read as a navigation
 * that never happened. So the first frame is a plain fade, and the slide is
 * left to real transitions.
 */
const booted = ref(false)
watch(showPage, (ready) => {
  if (ready) void nextTick(() => (booted.value = true))
}, { immediate: true })

const pageTransition = computed<string>(() => (booted.value ? 'admin-page' : 'admin-boot'))
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
        <Transition :name="pageTransition">
          <component v-if="showPage" :is="Component" />
          <AdminBootSkeleton v-else />
        </Transition>
      </router-view>
    </div>
  </AdminShell>
  <!-- Вне каркаса (login, 403) манифест не нужен — гейт не применяем. -->
  <router-view v-else v-slot="{ Component }">
    <Transition name="admin-page">
      <component :is="Component" />
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
 * The page host is a relative container. The leaving page is made absolute —
 * an overlay above the new one — while the entering page stays in the normal
 * flow and slides in from the right.
 *
 * The timings:
 *   - leaving fade-out: 140ms ease-out, so it goes quickly.
 *   - entering slide-from-right + fade-in: 320ms with cubic-bezier easing and
 *     a 60ms delay, so that the old page is gone before the new one reaches
 *     its position.
 *
 * overflow:hidden on the host keeps the slide from spilling past the viewport.
 */
.admin-page-host {
  position: relative;
  min-height: 200px;
  overflow: hidden;
}

/* A no-op stand-in until the manifest resolves — it replaces the potential flash
   NotFoundPage при reload deep-link. Top loading-bar показывает что
   что-то происходит. */
.admin-page-host__suspended {
  min-height: 320px;
}

/*
 * The first appearance after the shell has loaded: the skeleton and the page
 * occupy the same place, so this is a plain cross-fade with no movement.
 */
.admin-boot-enter-active {
  transition: opacity 200ms ease-out;
}
.admin-boot-enter-from {
  opacity: 0;
}
.admin-boot-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
  transition: opacity 140ms ease-out;
}
.admin-boot-leave-to {
  opacity: 0;
}

/* The leaving page is absolute: it does not move the layout and stays put while it fades. */
.admin-page-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
  transition: opacity 140ms ease-out;
}

.admin-page-leave-to {
  opacity: 0;
}

/* The entering page slides in from the right and fades in. The 60ms delay lets the old one almost finish leaving. */
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
  .admin-boot-enter-active,
  .admin-boot-leave-active,
  .admin-page-enter-active,
  .admin-page-leave-active {
    transition: opacity 80ms ease-out;
  }
  .admin-page-enter-from {
    transform: none;
  }
}
</style>

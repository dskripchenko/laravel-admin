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
import { provideLocale, ru as uidRu, en as uidEn } from '@dskripchenko/ui'
import { useLocaleStore } from '../stores/locale'
import { trSafe as tr } from '../stores/i18n'

const route = useRoute()
const auth = useAuthStore()
const brand = useBrand()

// UI-kit локаль следует локали панели (BL-11: «Выберите…» и другие
// встроенные строки примитивов @dskripchenko/ui при EN).
const localeStore = useLocaleStore()
provideLocale(computed(() => (localeStore.current === 'en' ? uidEn : uidRu)))

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
    adminToast.error(tr('Не удалось выйти из режима импертонации.'))
  }
}

const useShell = computed<boolean>(() => {
  if (route.meta?.fullscreen === true) return false
  if (route.meta?.kind === 'auth') return false
  if (route.name === 'admin.notFound') return false
  return true
})

/**
 * Пока каркас не собран, страницу не рендерим вовсе — на её месте скелет.
 *
 * Раньше гейт закрывал только вспышку 404 (deep-link резолвится в catch-all,
 * пока manifest не принёс динамические роуты). Но ровно та же дыра давала и
 * вспышку чужого экрана: без манифеста HomePage не знает про дашборды хоста
 * и рисует заглушку «зарегистрируйте DashboardScreen», которую через
 * четверть секунды сменяет настоящий дашборд. Замер на стенде: каркас на
 * 236 мс, настоящее содержимое на 510 мс.
 *
 * Поэтому гейт общий (useAppReady) и закрывает любую страницу до готовности
 * манифеста и меню — они грузятся параллельно и приезжают почти вместе.
 */
const appReady = useAppReady()
const showPage = appReady

/**
 * Первый показ страницы — это подмена скелета, а не переход между экранами:
 * уезжающий вбок скелет читался бы как навигация, которой не было. Поэтому
 * первый кадр меняем простым проявлением, а slide оставляем переходам.
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

/*
 * Первый показ после загрузки каркаса: скелет и страница занимают одно
 * место, поэтому чистое перекрестное проявление без сдвига.
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

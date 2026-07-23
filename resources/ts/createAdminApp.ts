/**
 * createAdminApp — фабрика-helper для host-проектов.
 *
 * Собирает Vue-app со всем нужным: Pinia, Router, AdminClient, hydrated
 * stores, built-in fields/layouts/widgets/infolist, default страницы.
 * Возвращает объект где `app` готов к `.mount('#admin-app')`.
 *
 * Минимальный пример (host's resources/js/admin.js):
 *
 *     import { createAdminApp } from '@dskripchenko/laravel-admin'
 *     import '@dskripchenko/laravel-admin/style.css'
 *
 *     const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
 *     app.mount('#admin-app')
 *
 * Расширенное использование с переопределением страниц:
 *
 *     const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__, {
 *       pages: { home: MyDashboard },
 *       onAppCreated: (app) => app.use(SomePlugin),
 *     })
 */

import { createApp, watch, type App, type Component } from 'vue'
import { createPinia } from 'pinia'
import { createWebHistory } from 'vue-router'

import AdminApp from './components/AdminApp.vue'
import HomePage from './components/HomePage.vue'
import ForbiddenPage from './components/ForbiddenPage.vue'
import NotFoundPage from './components/NotFoundPage.vue'
import SettingsPage from './components/SettingsPage.vue'
import ScreenPage from './components/ScreenPage.vue'
import { LoginPage } from './components/auth'
import ForgotPasswordPage from './components/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from './components/auth/ResetPasswordPage.vue'
import {
  ResourceIndexPage,
  ResourceFormPage,
  ResourceViewPage,
} from './components/resource'
import { ProfilePage } from './components/profile'
import { DashboardPage } from './components/dashboard'

import { createAdminClient, type AdminClient } from './api/client'
import { setAdminClient } from './stores'
import { BRAND_KEY } from './composables/useBrand'
import { useAuthStore } from './stores/auth'
import { useLocaleStore } from './stores/locale'
import { useI18nStore } from './stores/i18n'
import { useThemeStore } from './stores/theme'
import { useNotificationsStore } from './stores/notifications'
import { useManifestStore } from './stores/manifest'
import { useMenuStore } from './stores/menu'
import { useNavigationStore } from './stores/navigation'

import { createAdminRouter, type AdminRouter, type AdminRouterOptions } from './router'
import { registerBuiltinComponents } from './components/render/builtin'
import { registerBuiltinWidgets } from './components/dashboard/builtin'
import { registerBuiltinInfolistEntries } from './components/infolist/builtin'

import type { AdminBootstrap } from './types/bootstrap'

export interface CreateAdminAppPages {
  /** Login page. По умолчанию core'овский LoginPage. */
  login?: Component
  /** Главная (после login). По умолчанию HomePage. */
  home?: Component
  /** 403. По умолчанию ForbiddenPage. */
  forbidden?: Component
  /** 404. По умолчанию NotFoundPage. */
  notFound?: Component
  /** Профиль. По умолчанию ProfilePage. */
  profile?: Component
  /** Auth: forgot/reset password. По умолчанию core'овские. */
  forgotPassword?: Component
  resetPassword?: Component
  /** Resource index/form/view. По умолчанию core'овские. */
  resourceIndex?: Component
  resourceCreate?: Component
  resourceEdit?: Component
  resourceView?: Component
  /** Dashboard страница (для роутов admin.dashboard.*). */
  dashboard?: Component
  /** Settings page (для admin.settings.*). */
  settings?: Component
  /** Custom Screen page (для admin.screen.*). */
  screen?: Component
}

export interface CreateAdminAppOptions {
  /**
   * Базовый URL admin-панели. По умолчанию берётся из bootstrap.baseUrl
   * (если он pathname вида '/admin'), иначе '/admin'.
   */
  base?: string
  /** Override отдельных страниц. */
  pages?: CreateAdminAppPages
  /** Дополнительные опции router'а (extraRoutes, history, guards). */
  router?: Partial<Omit<AdminRouterOptions, 'components'>>
  /** Hook для до-настройки app перед mount (app.use(plugin), app.provide(...)). */
  onAppCreated?: (app: App) => void
  /**
   * Не вызывать manifestStore.load() автоматически. По умолчанию false —
   * createAdminApp сам подгружает manifest и выстраивает динамические роуты.
   */
  skipManifestLoad?: boolean
}

export interface AdminAppHandle {
  app: App
  router: AdminRouter
  client: AdminClient
}

/**
 * Создать Vue admin-app, готовый к `.mount('#admin-app')`.
 *
 * Шаги внутри:
 *  1. Pinia + AdminClient (axios через bootstrap.csrf/apiUrl).
 *  2. Hydrate auth/locale/theme/notifications stores из bootstrap.
 *  3. Регистрация built-in fields/layouts/widgets/infolist entries.
 *  4. Router со static-routes (login/home/profile/forbidden/notFound) +
 *     async manifest load → replaceManifestRoutes для resource/screen/
 *     settings/dashboard.
 *  5. Возврат `{ app, router, client }`. Host вызывает `app.mount(...)`.
 */
export function createAdminApp(
  bootstrap: AdminBootstrap,
  options: CreateAdminAppOptions = {},
): AdminAppHandle {
  const pages = options.pages ?? {}
  const base = options.base ?? deriveBase(bootstrap.baseUrl) ?? '/admin'

  // 1. AdminClient
  const client = createAdminClient({
    baseURL: bootstrap.apiUrl,
    csrfToken: bootstrap.csrf,
  })
  setAdminClient(client)

  // 2. Vue app + Pinia
  const app = createApp(AdminApp)
  const pinia = createPinia()
  app.use(pinia)

  // Брендинг (BL-12): провайдим bootstrap.brand в shell + применяем favicon.
  // Host кастомизирует чисто через config('admin.brand') — без патча UI.
  const brand = bootstrap.brand ?? {}
  app.provide(BRAND_KEY, brand)
  applyFavicon(brand.favicon)

  // 3. Pinia stores hydrate
  useAuthStore().hydrate(bootstrap)
  useLocaleStore().hydrate(bootstrap)
  useThemeStore().hydrate(bootstrap)
  useNotificationsStore().hydrate(bootstrap)
  useI18nStore().hydrate(bootstrap)

  // 4. Builtin field/layout/widget/infolist registries
  registerBuiltinComponents()
  registerBuiltinWidgets()
  registerBuiltinInfolistEntries()

  // 5. Router
  const router = createAdminRouter({
    base,
    history: options.router?.history ?? createWebHistory(base),
    components: {
      login: pages.login ?? LoginPage,
      home: pages.home ?? HomePage,
      forbidden: pages.forbidden ?? ForbiddenPage,
      notFound: pages.notFound ?? NotFoundPage,
      profile: pages.profile ?? ProfilePage,
      forgotPassword: pages.forgotPassword ?? ForgotPasswordPage,
      resetPassword: pages.resetPassword ?? ResetPasswordPage,
      resourceIndex: pages.resourceIndex ?? ResourceIndexPage,
      resourceCreate: pages.resourceCreate ?? ResourceFormPage,
      resourceEdit: pages.resourceEdit ?? ResourceFormPage,
      resourceView: pages.resourceView ?? ResourceViewPage,
      dashboard: pages.dashboard ?? DashboardPage,
      settings: pages.settings ?? SettingsPage,
      screen: pages.screen ?? ScreenPage,
    },
    extraRoutes: options.router?.extraRoutes,
    authGuard: options.router?.authGuard,
    titleGuard: options.router?.titleGuard,
  })
  app.use(router)

  // 5.1 Top loading-bar hooks: pending++ при старте навигации, pending--
  //     при её завершении.
  const navStore = useNavigationStore()
  router.beforeEach((to, from, next) => {
    if (to.fullPath !== from.fullPath) navStore.start()
    next()
  })
  router.afterEach(() => {
    navStore.end()
  })
  router.onError(() => {
    navStore.end()
  })

  // 5.2 Pre-fetch resource data ДО mount страницы. Это держит navigation в
  //     pending'е — старая страница остаётся в DOM пока новая загружает данные.
  //     После resolve hook'а Vue Router монтирует новую страницу с уже
  //     наполненным store'ом, что устраняет flash "пустая страница →
  //     данные приехали" (без необходимости в Suspense).
  router.beforeResolve(async (to, _from, next) => {
    // Только resource.index роуты предзагружают свой dataset.
    const name = typeof to.name === 'string' ? to.name : null
    if (name && name.startsWith('admin.resource.') && name.endsWith('.index')) {
      const slug = (to.params.slug as string | undefined) ?? (to.meta.slug as string | undefined)
      if (slug) {
        try {
          // Lazy-import чтобы избежать circular в createAdminApp init.
          const { useResourceIndexStore } = await import('./stores/resourceIndex')
          const indexStore = useResourceIndexStore()
          if (indexStore.slug !== slug || indexStore.items.length === 0) {
            indexStore.setSlug(slug)
            await indexStore.load().catch(() => undefined)
          }
        } catch {
          // silent — page mount упадёт в свой error-state.
        }
      }
    }
    next()
  })

  // 6. Manifest async-load + dynamic routes (только если authenticated;
  //    при появлении user после login — manifest подгружается автоматически).
  if (!options.skipManifestLoad) {
    const manifestStore = useManifestStore()
    const authStore = useAuthStore()

    const menuStore = useMenuStore()

    const loadAndApply = async (): Promise<void> => {
      // Параллельно с manifest'ом тянем sidebar-меню (от backend menu endpoint).
      void menuStore.load().catch(() => undefined)

      try {
        const manifest = await manifestStore.load()
        router.replaceManifestRoutes(manifest)
        // Если текущий route был разрешён в catch-all notFound (deep-link
        // на /r/articles/123/edit при первом mount, когда динамические
        // роуты ещё не были добавлены) — перерезолвим его теперь, когда
        // routes есть. Дожидаемся router.replace ДО того как выставим
        // bootResolved — иначе AdminApp на одном кадре увидит
        // (manifest !== null, route.name === 'admin.notFound') и сверкнёт 404.
        const current = router.currentRoute.value
        if (current.name === 'admin.notFound' && current.fullPath !== '/') {
          await router.replace(current.fullPath).catch(() => undefined)
        }
      } catch (error) {
        // Silent fail — host может перехватить через onAppCreated →
        // app.config.errorHandler. Manifest перезагрузится при следующем
        // появлении user (watch ниже).
        if (typeof console !== 'undefined') {
          console.error('[laravel-admin] manifest load failed:', error)
        }
      } finally {
        // Открываем гейт NotFoundPage. После этой точки реальный 404
        // (несуществующий URL) уже корректно отрисуется без вспышки.
        manifestStore.bootResolved = true
      }
    }

    if (authStore.isAuthenticated) {
      // Inline-bootstrap уже принёс user'а — грузим сразу.
      void loadAndApply()
    } else {
      // Login flow — manifest не нужен прямо сейчас; гейт открыт сразу,
      // чтобы LoginPage / NotFoundPage отрисовались штатно.
      manifestStore.bootResolved = true
    }
    // Если user'а ещё нет (login flow) либо появится позже —
    // подписываемся и грузим когда появится. immediate: false чтобы не
    // дублировать вызов при isAuthenticated=true выше.
    watch(
      () => authStore.user,
      (newUser, oldUser) => {
        if (newUser && !oldUser) {
          // Новый login → требуется свежий manifest, гейт сбрасываем.
          manifestStore.bootResolved = false
          void loadAndApply()
        }
      },
    )
  } else {
    // Host явно отключил manifest — гейт открыт сразу.
    useManifestStore().bootResolved = true
  }

  // 7. host hook
  options.onAppCreated?.(app)

  return { app, router, client }
}

/**
 * Извлечь base path из bootstrap.baseUrl ('http://app.test/admin' → '/admin').
 */
function deriveBase(baseUrl: string | undefined): string | null {
  if (!baseUrl) return null
  try {
    const url = new URL(baseUrl)
    return url.pathname || '/admin'
  } catch {
    return baseUrl.startsWith('/') ? baseUrl : null
  }
}

/**
 * Проставить favicon из config('admin.brand.favicon') — сервис под своей
 * иконкой во вкладке (BL-12). Idempotent: переиспользует существующий тег.
 */
function applyFavicon(href: string | null | undefined): void {
  if (typeof document === 'undefined' || !href) return
  let link = document.querySelector<HTMLLinkElement>('link[rel~="icon"]')
  if (link === null) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.href = href
}

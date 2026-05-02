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
import UnknownScreenPage from './components/UnknownScreenPage.vue'
import { LoginPage } from './components/auth'
import {
  ResourceIndexPage,
  ResourceFormPage,
  ResourceViewPage,
} from './components/resource'
import { ProfilePage } from './components/profile'
import { DashboardPage } from './components/dashboard'

import { createAdminClient, type AdminClient } from './api/client'
import { setAdminClient } from './stores'
import { useAuthStore } from './stores/auth'
import { useLocaleStore } from './stores/locale'
import { useThemeStore } from './stores/theme'
import { useNotificationsStore } from './stores/notifications'
import { useManifestStore } from './stores/manifest'

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

  // 3. Pinia stores hydrate
  useAuthStore().hydrate(bootstrap)
  useLocaleStore().hydrate(bootstrap)
  useThemeStore().hydrate(bootstrap)
  useNotificationsStore().hydrate(bootstrap)

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
      resourceIndex: pages.resourceIndex ?? ResourceIndexPage,
      resourceCreate: pages.resourceCreate ?? ResourceFormPage,
      resourceEdit: pages.resourceEdit ?? ResourceFormPage,
      resourceView: pages.resourceView ?? ResourceViewPage,
      dashboard: pages.dashboard ?? DashboardPage,
      settings: pages.settings ?? SettingsPage,
      screen: pages.screen ?? UnknownScreenPage,
    },
    extraRoutes: options.router?.extraRoutes,
    authGuard: options.router?.authGuard,
    titleGuard: options.router?.titleGuard,
  })
  app.use(router)

  // 6. Manifest async-load + dynamic routes (только если authenticated;
  //    при появлении user после login — manifest подгружается автоматически).
  if (!options.skipManifestLoad) {
    const manifestStore = useManifestStore()
    const authStore = useAuthStore()

    const loadAndApply = (): void => {
      void manifestStore
        .load()
        .then((manifest) => {
          router.replaceManifestRoutes(manifest)
          // Если текущий route был разрешён в catch-all notFound (deep-link
          // на /r/articles/123/edit при первом mount, когда динамические
          // роуты ещё не были добавлены) — перерезолвим его теперь, когда
          // routes есть.
          const current = router.currentRoute.value
          if (current.name === 'admin.notFound' && current.fullPath !== '/') {
            void router.replace(current.fullPath)
          }
        })
        .catch((error: unknown) => {
          // Silent fail — host может перехватить через onAppCreated →
          // app.config.errorHandler. Manifest перезагрузится при следующем
          // появлении user (watch ниже).
          if (typeof console !== 'undefined') {
            console.error('[laravel-admin] manifest load failed:', error)
          }
        })
    }

    if (authStore.isAuthenticated) {
      // Inline-bootstrap уже принёс user'а — грузим сразу.
      loadAndApply()
    }
    // Если user'а ещё нет (login flow) либо появится позже —
    // подписываемся и грузим когда появится. immediate: false чтобы не
    // дублировать вызов при isAuthenticated=true выше.
    watch(
      () => authStore.user,
      (newUser, oldUser) => {
        if (newUser && !oldUser) {
          loadAndApply()
        }
      },
    )
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

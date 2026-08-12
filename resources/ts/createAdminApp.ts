/**
 * createAdminApp — the factory helper for host projects.
 *
 * Assembles a Vue app with everything it needs: Pinia, the router, AdminClient,
 * hydrated stores, the built-in fields/layouts/widgets/infolist entries and the
 * default pages. Returns an object whose `app` is ready for
 * `.mount('#admin-app')`.
 *
 * The minimal example, in a host's resources/js/admin.js:
 *
 *     import { createAdminApp } from '@dskripchenko/laravel-admin'
 *     import '@dskripchenko/laravel-admin/style.css'
 *
 *     const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
 *     app.mount('#admin-app')
 *
 * A fuller example, with pages overridden:
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
import { NotificationsPage } from './components/notifications'
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
  /** The login page; the library's LoginPage by default. */
  login?: Component
  /** The landing page after login; HomePage by default. */
  home?: Component
  /** 403; ForbiddenPage by default. */
  forbidden?: Component
  /** 404; NotFoundPage by default. */
  notFound?: Component
  /** Notifications; NotificationsPage by default. */
  notifications?: Component

  /** The profile; ProfilePage by default. */
  profile?: Component
  /** Auth: forgot and reset password; the library's own by default. */
  forgotPassword?: Component
  resetPassword?: Component
  /** Resource index/form/view; the library's own by default. */
  resourceIndex?: Component
  resourceCreate?: Component
  resourceEdit?: Component
  resourceView?: Component
  /** The dashboard page, behind the admin.dashboard.* routes. */
  dashboard?: Component
  /** The settings page, behind admin.settings.*. */
  settings?: Component
  /** The custom screen page, behind admin.screen.*. */
  screen?: Component
}

export interface CreateAdminAppOptions {
  /**
   * Base URL of the admin panel. Taken from bootstrap.baseUrl when that is a
   * pathname such as '/admin', otherwise '/admin'.
   */
  base?: string
  /** Overrides for individual pages. */
  pages?: CreateAdminAppPages
  /** Extra router options: extraRoutes, history, guards. */
  router?: Partial<Omit<AdminRouterOptions, 'components'>>
  /** A hook to finish configuring the app before mount: app.use(plugin), app.provide(…). */
  onAppCreated?: (app: App) => void
  /**
   * Do not call manifestStore.load() automatically. False by default:
   * createAdminApp loads the manifest itself and builds the dynamic routes.
   */
  skipManifestLoad?: boolean
}

export interface AdminAppHandle {
  app: App
  router: AdminRouter
  client: AdminClient
}

/**
 * Creates the Vue admin app, ready for `.mount('#admin-app')`.
 *
 * What happens inside:
 *  1. Pinia and AdminClient — axios wired to bootstrap.csrf and apiUrl.
 *  2. The auth/locale/theme/notifications stores are hydrated from bootstrap.
 *  3. The built-in fields, layouts, widgets and infolist entries register.
 *  4. The router is created with the static routes (login, home, profile,
 *     forbidden, notFound); the manifest then loads asynchronously and
 *     replaceManifestRoutes adds the resource/screen/settings/dashboard ones.
 *  5. `{ app, router, client }` is returned; the host calls `app.mount(…)`.
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

    /**
     * A 401 from any panel request leads to the login page, by full navigation.
     *
     * The handler had been in the client's options from the very beginning and
     * NOBODY ever passed it: a 401 was simply dropped. An expired session left
     * the visitor inside a live shell with an empty menu — the shell was
     * already in the browser, and the menu and the manifest came back refused.
     * A second reload happened to fix it, which no one can be expected to
     * guess.
     *
     * Why `window.location` and not the router: the refusal arrives during the
     * load itself, while the menu and the manifest are being fetched. The
     * router may not exist yet, and when it does it is busy with the initial
     * navigation and swallows the replace. Verified on a stand: with the
     * router the visitor stayed on the old address looking at a 404.
     *
     * A full reload is also the more honest answer here: the session is dead,
     * and whatever is in memory belongs to the tab's previous owner — there is
     * no reason to carry it to the login form.
     */
    onUnauthenticated: () => {
      if (typeof window === 'undefined') return

      const path = window.location.pathname
      // On the login and password-recovery pages a 401 is normal.
      if (path.endsWith('/login') || path.includes('/forgot-password') || path.includes('/reset-password')) {
        return
      }

      const from = window.location.pathname + window.location.search
      const back = from.startsWith(base) ? from.slice(base.length) || '/' : from

      window.location.assign(`${base}/login?redirect=${encodeURIComponent(back)}`)
    },
  })
  setAdminClient(client)

  // 2. Vue app + Pinia
  const app = createApp(AdminApp)
  const pinia = createPinia()
  app.use(pinia)

  // Branding: bootstrap.brand is provided to the shell and the favicon is
  // applied. A host customises it purely through config('admin.brand'), with
  // no patching of the UI.
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
      // The page used to be wired up ONLY when a host passed one, and no host
      // ever did — `/notifications` answered 404 in every panel. Nothing
      // linked there, so the hole never surfaced; and there was nowhere to
      // look through the history either, since the drawer closes on click.
      notifications: pages.notifications ?? NotificationsPage,
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

  // 5.1 Top loading-bar hooks: pending++ when a navigation starts,
  //     pending-- when it ends.
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

  // 5.2 Pre-fetch the resource data BEFORE the page mounts. This keeps the
  //     navigation pending, so the old page stays in the DOM while the new one
  //     loads. After the resolve hook Vue Router mounts the new page with the
  //     store already filled, which removes the "blank page → data arrived"
  //     flash without needing Suspense.
  router.beforeResolve(async (to, _from, next) => {
    // Only resource.index routes pre-load their dataset.
    const name = typeof to.name === 'string' ? to.name : null
    if (name && name.startsWith('admin.resource.') && name.endsWith('.index')) {
      const slug = (to.params.slug as string | undefined) ?? (to.meta.slug as string | undefined)
      if (slug) {
        try {
          // A lazy import, to avoid a cycle during createAdminApp init.
          const { useResourceIndexStore } = await import('./stores/resourceIndex')
          const indexStore = useResourceIndexStore()
          if (indexStore.slug !== slug || indexStore.items.length === 0) {
            indexStore.setSlug(slug)
            await indexStore.load().catch(() => undefined)
          }
        } catch {
          // Silent: the page mount will fall into its own error state.
        }
      }
    }
    next()
  })

  // 6. Manifest loading and dynamic routes — only when authenticated. Once a
  //    user appears after login the manifest is fetched automatically.
  if (!options.skipManifestLoad) {
    const manifestStore = useManifestStore()
    const authStore = useAuthStore()

    const menuStore = useMenuStore()

    const loadAndApply = async (): Promise<void> => {
      // The sidebar menu is fetched in parallel with the manifest, from the
      // backend's menu endpoint.
      void menuStore.load().catch(() => undefined)

      try {
        const manifest = await manifestStore.load()
        router.replaceManifestRoutes(manifest)
        // If the current route resolved into the catch-all notFound — a deep
        // link to /r/articles/123/edit on the first mount, when the dynamic
        // routes were not registered yet — resolve it again now that they are.
        // The router.replace is awaited BEFORE bootResolved is set: otherwise
        // AdminApp would see (manifest !== null, route.name ===
        // 'admin.notFound') for one frame and flash a 404.
        const current = router.currentRoute.value
        if (current.name === 'admin.notFound' && current.fullPath !== '/') {
          await router.replace(current.fullPath).catch(() => undefined)
        }
      } catch (error) {
        // Fails silently — a host can intercept it through onAppCreated and
        // app.config.errorHandler. The manifest reloads the next time a user
        // appears; see the watch below.
        if (typeof console !== 'undefined') {
          console.error('[laravel-admin] manifest load failed:', error)
        }
      } finally {
        // Open the NotFoundPage gate. Past this point a genuine 404 — an
        // address that really does not exist — renders properly, with no
        // flash.
        manifestStore.bootResolved = true
      }
    }

    if (authStore.isAuthenticated) {
      // The inline bootstrap already brought a user — load right away.
      void loadAndApply()
    } else {
      // The login flow needs no manifest yet, and the gate opens immediately
      // so that LoginPage and NotFoundPage render as they should.
      manifestStore.bootResolved = true
    }
    // When there is no user yet (the login flow) or one appears later, we
    // subscribe and load then. immediate: false so the call above, made when
    // isAuthenticated was already true, is not duplicated.
    watch(
      () => authStore.user,
      (newUser, oldUser) => {
        if (newUser && !oldUser) {
          // A fresh login needs a fresh manifest, so the gate closes again.
          manifestStore.bootResolved = false
          void loadAndApply()
        }
      },
    )
  } else {
    // The host switched the manifest off explicitly — the gate opens at once.
    useManifestStore().bootResolved = true
  }

  // 7. host hook
  options.onAppCreated?.(app)

  return { app, router, client }
}

/**
 * Extracts the base path from bootstrap.baseUrl:
 * 'http://app.test/admin' → '/admin'.
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
 * Sets the favicon from config('admin.brand.favicon'), so the service wears its
 * own icon in the browser tab. Idempotent: an existing tag is reused.
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

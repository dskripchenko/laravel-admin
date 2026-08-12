/**
 * createAdminRouter — the factory of the admin router.
 *
 * Usage:
 *
 *     const router = createAdminRouter({
 *       base: '/admin',
 *       components: { ... },
 *       brand: 'My App',
 *     })
 *     await router.replaceManifestRoutes(manifestStore.manifest)
 *     app.use(router)
 *
 * The library deliberately does not pick createWebHistory for the host: the
 * host passes the history object itself (createWebHistory or
 * createWebHashHistory).
 */

import {
  createRouter,
  createWebHistory,
  type Router,
  type RouteRecordRaw,
  type RouterHistory,
} from 'vue-router'
import type { AdminManifest } from '../stores/manifest'
import { buildRoutesFromManifest, type RouteComponentResolver, type AdminRouteComponent } from './builder'
import { createAuthGuard, createTitleGuard, type AuthGuardOptions, type TitleGuardOptions } from './guards'

export interface AdminRouterOptions {
  /** The admin panel's base URL; '/admin' by default. */
  base?: string
  /** The history implementation; createWebHistory(base) by default. */
  history?: RouterHistory
  /** The component resolver for the resource, screen, settings and dashboard routes. */
  components: RouteComponentResolver & {
    /** Login. */
    login: AdminRouteComponent
    /** The home page, or the overview dashboard. */
    home: AdminRouteComponent
    /** 403 forbidden. */
    forbidden: AdminRouteComponent
    /** 404 not found. */
    notFound: AdminRouteComponent
    /** The profile page. */
    profile?: AdminRouteComponent
    /** The notifications page. */
    notifications?: AdminRouteComponent
    /** Forgot password. */
    forgotPassword?: AdminRouteComponent
    /** Password reset, by the token and email from the query. */
    resetPassword?: AdminRouteComponent
  }
  /** Extra routes added on top of the dynamic ones. */
  extraRoutes?: RouteRecordRaw[]
  /** The auth guard's options. */
  authGuard?: AuthGuardOptions
  /** The title guard's options. */
  titleGuard?: TitleGuardOptions
}

/**
 * A Router extended with the ability to rebuild the dynamic routes.
 */
export interface AdminRouter extends Router {
  /**
   * Rebuilds the dynamic routes from the manifest: the old
   * admin.resource.* / admin.screen.* / admin.settings.* / admin.dashboard.*
   * are removed and replaced with the current ones. Useful when the manifest
   * is reloaded.
   */
  replaceManifestRoutes(manifest: AdminManifest | null): void
}

/**
 * The prefixes of the dynamic routes that replaceManifestRoutes swaps out.
 */
const DYNAMIC_NAME_PREFIXES = [
  'admin.resource.',
  'admin.screen.',
  'admin.settings.',
  'admin.dashboard.',
]

function isDynamicRouteName(name: unknown): boolean {
  if (typeof name !== 'string') return false
  return DYNAMIC_NAME_PREFIXES.some((p) => name.startsWith(p))
}

export function createAdminRouter(opts: AdminRouterOptions): AdminRouter {
  const base = opts.base ?? '/admin'
  const history = opts.history ?? createWebHistory(base)

  const staticRoutes: RouteRecordRaw[] = [
    {
      path: '/login',
      name: 'admin.login',
      component: opts.components.login,
      meta: { kind: 'auth', title: 'Вход' },
    },
    {
      path: '/',
      name: 'admin.home',
      component: opts.components.home,
      meta: { requiresAuth: true, kind: 'system', title: 'Главная' },
    },
    {
      path: '/forbidden',
      name: 'admin.forbidden',
      component: opts.components.forbidden,
      meta: { requiresAuth: true, kind: 'system', title: '403 — доступ запрещён' },
    },
  ]

  if (opts.components.profile) {
    staticRoutes.push({
      path: '/profile',
      name: 'admin.profile',
      component: opts.components.profile,
      meta: { requiresAuth: true, kind: 'system', title: 'Профиль' },
    })
  }

  if (opts.components.notifications) {
    staticRoutes.push({
      path: '/notifications',
      name: 'admin.notifications',
      component: opts.components.notifications,
      meta: { requiresAuth: true, kind: 'system', title: 'Уведомления' },
    })
  }

  if (opts.components.forgotPassword) {
    staticRoutes.push({
      path: '/forgot-password',
      name: 'admin.forgotPassword',
      component: opts.components.forgotPassword,
      meta: { kind: 'auth', title: 'Восстановление пароля' },
    })
  }
  if (opts.components.resetPassword) {
    staticRoutes.push({
      path: '/reset-password',
      name: 'admin.resetPassword',
      component: opts.components.resetPassword,
      meta: { kind: 'auth', title: 'Новый пароль' },
    })
  }

  // The catch-all 404 goes last: vue-router matches in order, and the dynamic
  // routes may be added later through addRoute.
  //
  // requiresAuth here is not about protecting the data — the backend does that
  // — but about the answer being truthful: the resource routes exist only
  // after the manifest, and a guest never loads the manifest at all. Without
  // this flag a guest following a direct link to `/r/templates` landed in the
  // catch-all and got "404 — no such page" instead of the login form: the
  // section looked non-existent when in fact it merely required signing in.
  const notFoundRoute: RouteRecordRaw = {
    path: '/:pathMatch(.*)*',
    name: 'admin.notFound',
    component: opts.components.notFound,
    meta: { kind: 'system', title: '404', requiresAuth: true },
  }

  const router = createRouter({
    history,
    routes: [...staticRoutes, ...(opts.extraRoutes ?? []), notFoundRoute],
  }) as AdminRouter

  router.beforeEach(createAuthGuard(opts.authGuard))
  router.afterEach(createTitleGuard(opts.titleGuard))

  router.replaceManifestRoutes = (manifest: AdminManifest | null): void => {
    // Remove every old dynamic route by name.
    const allRoutes = router.getRoutes()
    for (const route of allRoutes) {
      if (isDynamicRouteName(route.name)) {
        router.removeRoute(route.name as string)
      }
    }

    // Remove the catch-all, to add it back last.
    if (router.hasRoute('admin.notFound')) {
      router.removeRoute('admin.notFound')
    }

    const dynamic = buildRoutesFromManifest(manifest, opts.components)
    for (const route of dynamic) {
      router.addRoute(route)
    }
    router.addRoute(notFoundRoute)
  }

  return router
}

export { buildRoutesFromManifest } from './builder'
export type { RouteComponentResolver, RouteMeta, AdminRouteComponent } from './builder'
export { createAuthGuard, createTitleGuard } from './guards'
export type { AuthGuardOptions, TitleGuardOptions } from './guards'

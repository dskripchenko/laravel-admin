/**
 * createAdminRouter — фабрика admin-router'а.
 *
 * Использование:
 *
 *     const router = createAdminRouter({
 *       base: '/admin',
 *       components: { ... },
 *       brand: 'My App',
 *     })
 *     await router.replaceManifestRoutes(manifestStore.manifest)
 *     app.use(router)
 *
 * Library намеренно не делает createWebHistory выбор за host'а — host передаёт
 * объект history (createWebHistory / createWebHashHistory).
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
  /** Base URL admin-панели. По умолчанию '/admin'. */
  base?: string
  /** History-implementation. По умолчанию createWebHistory(base). */
  history?: RouterHistory
  /** Component resolver для resource/screen/settings/dashboard роутов. */
  components: RouteComponentResolver & {
    /** Login. */
    login: AdminRouteComponent
    /** Главная (либо overview-dashboard). */
    home: AdminRouteComponent
    /** 403 forbidden. */
    forbidden: AdminRouteComponent
    /** 404 not found. */
    notFound: AdminRouteComponent
    /** Страница профиля. */
    profile?: AdminRouteComponent
    /** Страница уведомлений. */
    notifications?: AdminRouteComponent
  }
  /** Дополнительные руты сверху динамики. */
  extraRoutes?: RouteRecordRaw[]
  /** Опции auth-guard. */
  authGuard?: AuthGuardOptions
  /** Опции title-guard. */
  titleGuard?: TitleGuardOptions
}

/**
 * Расширенный Router с возможностью пере-построить динамические роуты.
 */
export interface AdminRouter extends Router {
  /**
   * Пере-собрать динамические роуты из manifest'а.
   * Удаляет старые admin.resource.* / admin.screen.* / admin.settings.* / admin.dashboard.*
   * и заменяет на актуальные. Полезно при перезагрузке manifest'а.
   */
  replaceManifestRoutes(manifest: AdminManifest | null): void
}

/**
 * Префиксы dynamic-роутов которые подменяются при replaceManifestRoutes.
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

  // Catch-all 404 кладём в конец — vue-router matches in order и динамика
  // может быть добавлена позже через addRoute.
  const notFoundRoute: RouteRecordRaw = {
    path: '/:pathMatch(.*)*',
    name: 'admin.notFound',
    component: opts.components.notFound,
    meta: { kind: 'system', title: '404' },
  }

  const router = createRouter({
    history,
    routes: [...staticRoutes, ...(opts.extraRoutes ?? []), notFoundRoute],
  }) as AdminRouter

  router.beforeEach(createAuthGuard(opts.authGuard))
  router.afterEach(createTitleGuard(opts.titleGuard))

  router.replaceManifestRoutes = (manifest: AdminManifest | null): void => {
    // Удаляем все старые динамические роуты по name.
    const allRoutes = router.getRoutes()
    for (const route of allRoutes) {
      if (isDynamicRouteName(route.name)) {
        router.removeRoute(route.name as string)
      }
    }

    // Удаляем catch-all чтобы потом добавить его обратно последним.
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

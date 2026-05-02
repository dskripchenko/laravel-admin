/**
 * buildRoutesFromManifest — превращает AdminManifest в массив RouteRecordRaw.
 *
 * Маппинг:
 *   - resource{slug}      → /r/{slug}                         (index)
 *                           /r/{slug}/create                  (create)
 *                           /r/{slug}/:id/edit                (edit)
 *                           /r/{slug}/:id                     (view, опционально)
 *   - screen{slug}        → /screens/{slug}
 *   - settings{slug}      → /settings/{slug}
 *   - dashboard{slug}     → /dashboard/{slug}
 *
 * Component'ы передаются через resolver — host-проект решает, что отрисовать.
 * Это позволяет library не тащить рендереры внутрь себя жёстко.
 */

import type { RouteRecordRaw } from 'vue-router'
import type { Component } from 'vue'
import type {
  AdminManifest,
  ManifestResourceMeta,
  ManifestScreenMeta,
  ManifestSettingsMeta,
} from '../stores/manifest'

/** Vue Component либо async-loader (для code-splitting). */
export type AdminRouteComponent = Component | (() => Promise<Component>)

export interface RouteMeta {
  /** Требует залогиненного пользователя. */
  requiresAuth?: boolean
  /** Permissions, нужные для входа. ANY-логика (хотя бы одно совпадение). */
  permissions?: string[]
  /** Заголовок страницы (рендерим в <title> через router.afterEach). */
  title?: string
  /** Тип админ-роута — для UI breadcrumbs/active-state. */
  kind?: 'resource' | 'screen' | 'settings' | 'dashboard' | 'system' | 'auth'
  /** Slug сущности из манифеста — для извлечения мета внутри компонента. */
  slug?: string
}

declare module 'vue-router' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface RouteMeta extends Record<string, unknown> {}
}

/**
 * Resolver компонентов для динамических роутов.
 *
 * Host-проект передаёт конкретные Vue-компоненты для каждой роли.
 * Library не зависит от конкретных view'ов.
 */
export interface RouteComponentResolver {
  resourceIndex: AdminRouteComponent
  resourceCreate: AdminRouteComponent
  resourceEdit: AdminRouteComponent
  resourceView: AdminRouteComponent
  screen: AdminRouteComponent
  settings: AdminRouteComponent
  dashboard: AdminRouteComponent
}

/**
 * Извлекает permissions из meta'ы ресурса / скрина / settings'а.
 *
 * resource.permissions = { view: 'admin.users.view', ... } → ['admin.users.view']
 * (для index используется view; для create/edit — соответственно).
 */
function pickResourcePermission(
  resource: ManifestResourceMeta,
  ability: 'view' | 'create' | 'update',
): string[] {
  const perm = resource.permissions?.[ability]
  return typeof perm === 'string' && perm.length > 0 ? [perm] : []
}

function pickSettingsPermission(s: ManifestSettingsMeta, ability: 'view' | 'update'): string[] {
  const perm = s.permissions?.[ability]
  return typeof perm === 'string' && perm.length > 0 ? [perm] : []
}

function pickScreenPermission(s: ManifestScreenMeta): string[] {
  if (Array.isArray(s.permission)) return s.permission
  if (typeof s.permission === 'string' && s.permission.length > 0) return [s.permission]
  return []
}

function buildResourceRoutes(
  resource: ManifestResourceMeta,
  components: RouteComponentResolver,
): RouteRecordRaw[] {
  const slug = resource.slug
  const base = `/r/${slug}`

  return [
    {
      path: base,
      name: `admin.resource.${slug}.index`,
      component: components.resourceIndex,
      meta: {
        requiresAuth: true,
        kind: 'resource',
        slug,
        title: resource.label,
        permissions: pickResourcePermission(resource, 'view'),
      },
      // slug передаётся в page-component'ы как prop. Конкретный slug запекаем в
      // function-mode, чтобы page видел его одинаково и через named-route, и через
      // path-routing (без params).
      props: { slug },
    },
    {
      path: `${base}/create`,
      name: `admin.resource.${slug}.create`,
      component: components.resourceCreate,
      meta: {
        requiresAuth: true,
        kind: 'resource',
        slug,
        title: `${resource.label}: создать`,
        permissions: pickResourcePermission(resource, 'create'),
      },
      props: { slug },
    },
    {
      path: `${base}/:id/edit`,
      name: `admin.resource.${slug}.edit`,
      component: components.resourceEdit,
      meta: {
        requiresAuth: true,
        kind: 'resource',
        slug,
        title: `${resource.label}: редактирование`,
        permissions: pickResourcePermission(resource, 'update'),
      },
      // slug запекаем, id из route-params.
      props: (route) => ({ slug, id: route.params.id }),
    },
    {
      path: `${base}/:id`,
      name: `admin.resource.${slug}.view`,
      component: components.resourceView,
      meta: {
        requiresAuth: true,
        kind: 'resource',
        slug,
        title: resource.label,
        permissions: pickResourcePermission(resource, 'view'),
      },
      props: (route) => ({ slug, id: route.params.id }),
    },
  ]
}

function buildScreenRoute(
  screen: ManifestScreenMeta,
  components: RouteComponentResolver,
): RouteRecordRaw {
  return {
    path: `/screens/${screen.slug}`,
    name: `admin.screen.${screen.slug}`,
    component: components.screen,
    meta: {
      requiresAuth: true,
      kind: 'screen',
      slug: screen.slug,
      title: screen.name,
      permissions: pickScreenPermission(screen),
    },
  }
}

function buildSettingsRoute(
  settings: ManifestSettingsMeta,
  components: RouteComponentResolver,
): RouteRecordRaw {
  return {
    path: `/settings/${settings.slug}`,
    name: `admin.settings.${settings.slug}`,
    component: components.settings,
    meta: {
      requiresAuth: true,
      kind: 'settings',
      slug: settings.slug,
      title: settings.label,
      permissions: pickSettingsPermission(settings, 'view'),
    },
  }
}

interface DashboardMeta {
  slug?: string
  label?: string
  permission?: string | string[] | null
}

function buildDashboardRoute(
  dashboard: DashboardMeta,
  components: RouteComponentResolver,
): RouteRecordRaw | null {
  if (typeof dashboard.slug !== 'string' || dashboard.slug.length === 0) return null
  const permission = dashboard.permission
  const permissions = Array.isArray(permission)
    ? permission
    : typeof permission === 'string' && permission.length > 0
      ? [permission]
      : []
  return {
    path: `/dashboard/${dashboard.slug}`,
    name: `admin.dashboard.${dashboard.slug}`,
    component: components.dashboard,
    meta: {
      requiresAuth: true,
      kind: 'dashboard',
      slug: dashboard.slug,
      title: dashboard.label ?? 'Dashboard',
      permissions,
    },
  }
}

export function buildRoutesFromManifest(
  manifest: AdminManifest | null,
  components: RouteComponentResolver,
): RouteRecordRaw[] {
  if (manifest === null) return []

  const routes: RouteRecordRaw[] = []

  for (const resource of manifest.resources) {
    routes.push(...buildResourceRoutes(resource, components))
  }
  for (const screen of manifest.screens) {
    routes.push(buildScreenRoute(screen, components))
  }
  for (const s of manifest.settings) {
    routes.push(buildSettingsRoute(s, components))
  }
  for (const d of manifest.dashboards as DashboardMeta[]) {
    const route = buildDashboardRoute(d, components)
    if (route) routes.push(route)
  }

  return routes
}

/**
 * buildRoutesFromManifest turns an AdminManifest into an array of
 * RouteRecordRaw.
 *
 * The mapping:
 *   - resource{slug}      → /r/{slug}                         (index)
 *                           /r/{slug}/create                  (create)
 *                           /r/{slug}/:id/edit                (edit)
 *                           /r/{slug}/:id                     (view, optional)
 *   - screen{slug}        → /screens/{slug}
 *   - settings{slug}      → /settings/{slug}
 *   - dashboard{slug}     → /dashboard/{slug}
 *
 * The components come from a resolver, so the host project decides what to
 * draw and the library carries no renderers of its own.
 */

import type { RouteRecordRaw } from 'vue-router'
import type { Component } from 'vue'
import type {
  AdminManifest,
  ManifestResourceMeta,
  ManifestScreenMeta,
  ManifestSettingsMeta,
} from '../stores/manifest'

/** A Vue component, or an async loader for code splitting. */
export type AdminRouteComponent = Component | (() => Promise<Component>)

export interface RouteMeta {
  /** Requires a logged-in user. */
  requiresAuth?: boolean
  /** The permissions required to enter, with ANY semantics: one match is enough. */
  permissions?: string[]
  /** The page's title, rendered into <title> by router.afterEach. */
  title?: string
  /** The admin route's kind, for the breadcrumbs and the active state in the UI. */
  kind?: 'resource' | 'screen' | 'settings' | 'dashboard' | 'system' | 'auth'
  /** The entity's slug from the manifest, so the component can find its meta. */
  slug?: string
}

declare module 'vue-router' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface RouteMeta extends Record<string, unknown> {}
}

/**
 * The component resolver of the dynamic routes.
 *
 * The host project supplies a Vue component for each role, and the library
 * depends on no particular views.
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
 * Extracts the permissions from a resource's, a screen's or a settings page's
 * meta.
 *
 * resource.permissions = { view: 'admin.users.view', ... } → ['admin.users.view'],
 * where the index uses view and create and edit use their own.
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
      // The slug reaches the page components as a prop. It is baked in
      // through the function mode, so that the page sees the same thing
      // whether it was reached by name or by path, where there are no params.
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
      // The slug is baked in, the id comes from the route params.
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

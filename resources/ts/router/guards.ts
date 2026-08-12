/**
 * The router guards: authentication and permissions.
 *
 * The logic:
 *   1. When route.meta.requiresAuth is set and nobody is logged in, go to
 *      /login with ?redirect=...
 *   2. When the user has a pendingChallenge (2FA), every protected route
 *      redirects to /login, where the challenge form lives.
 *   3. When route.meta.permissions is set, hasAnyPermission() decides; none of
 *      them means /forbidden (or the route named 'admin.forbidden').
 *
 * The guards use useAuthStore but never touch the client directly: everything
 * goes through the permissions, isAuthenticated and pendingChallenge state.
 * The stores must be hydrated before the router is set up.
 */

import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import { trSafe } from '../stores/i18n'
import { useAuthStore } from '../stores/auth'

/** What a guard returns: true to pass through, or a redirect target. */
type GuardResult = boolean | RouteLocationRaw

/**
 * A plain three-argument function, which is what router.beforeEach accepts. We
 * do not use NavigationGuardWithThis<undefined> because it demands
 * `this: undefined`, which is awkward when calling it directly from tests.
 */
type SimpleGuard = (
  to: RouteLocationNormalized,
  from: RouteLocationNormalized,
  next?: unknown,
) => GuardResult

export interface AuthGuardOptions {
  /** The name of the login route; 'admin.login' by default. */
  loginRouteName?: string
  /** The name of the 403 page's route; 'admin.forbidden' by default. */
  forbiddenRouteName?: string
  /**
   * The name of the query parameter that carries the page to return to after
   * the login; 'redirect' by default.
   */
  redirectQueryKey?: string
}

/**
 * Creates the beforeEach guard. Pinia must be the active instance by the time
 * the guard runs: router.beforeEach fires on every navigation, the first one
 * included, so Pinia has to be installed already.
 */
export function createAuthGuard(opts: AuthGuardOptions = {}): SimpleGuard {
  const loginRouteName = opts.loginRouteName ?? 'admin.login'
  const forbiddenRouteName = opts.forbiddenRouteName ?? 'admin.forbidden'
  const redirectQueryKey = opts.redirectQueryKey ?? 'redirect'

  return (to: RouteLocationNormalized) => {
    // The login route is always reachable.
    if (to.name === loginRouteName) {
      return true
    }

    const auth = useAuthStore()
    const requiresAuth = to.meta?.requiresAuth === true

    if (requiresAuth && !auth.isAuthenticated) {
      return {
        name: loginRouteName,
        query: { [redirectQueryKey]: to.fullPath },
      }
    }

    // Logged in but in the middle of 2FA: nowhere to go but the login.
    if (auth.isChallengePending) {
      return {
        name: loginRouteName,
        query: { [redirectQueryKey]: to.fullPath },
      }
    }

    // The permission check, with ANY semantics: one match is enough.
    const permissionsMeta = to.meta?.permissions
    if (Array.isArray(permissionsMeta) && permissionsMeta.length > 0) {
      const allowed = auth.hasAnyPermission(permissionsMeta as string[])
      if (!allowed) {
        return { name: forbiddenRouteName }
      }
    }

    return true
  }
}

export interface TitleGuardOptions {
  /** The title template: {title} is meta.title, {brand} is the brand's name. */
  template?: string
  /** The brand's name. */
  brand?: string
  /** The title to use when meta carries none. */
  fallback?: string
}

/**
 * Creates the afterEach hook that updates document.title.
 *
 * The default template is '{title} · {brand}' when both are there, and
 * whichever one is present otherwise.
 */
export function createTitleGuard(
  opts: TitleGuardOptions = {},
): (to: RouteLocationNormalized, from?: RouteLocationNormalized, failure?: unknown) => void {
  const template = opts.template
  const brand = opts.brand ?? ''
  const fallback = opts.fallback ?? ''

  return (to) => {
    if (typeof document === 'undefined') return
    // meta.title goes into document.title — the single place where the titles
    // of all the system routes are translated.
    const t = trSafe((to.meta?.title as string | undefined) ?? fallback)
    let title: string
    if (template) {
      title = template.replace('{title}', t).replace('{brand}', brand)
    } else if (t && brand) {
      title = `${t} · ${brand}`
    } else {
      title = t || brand
    }
    document.title = title
  }
}

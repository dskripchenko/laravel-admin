/**
 * Router-guards: auth + permissions.
 *
 * Логика:
 *   1. Если route.meta.requiresAuth и пользователь не залогинен → /login
 *      с ?redirect=...
 *   2. Если у user'а есть pendingChallenge (2FA) — все защищённые роуты
 *      редиректят на /login (challenge-form там).
 *   3. Если route.meta.permissions заданы — проверяем hasAnyPermission().
 *      Нет ни одного → /forbidden (или роут с name='admin.forbidden').
 *
 * Guards используют useAuthStore — но НЕ берут client напрямую: всё через
 * permissions/isAuthenticated/pendingChallenge state. Stores должны быть
 * захайдрейчены до setup'а router'а.
 */

import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'

/** Тип возвращаемого значения guard'а — true (passthrough) либо redirect-target. */
type GuardResult = boolean | RouteLocationRaw

/**
 * Простая 3-арг функция (то что router.beforeEach принимает).
 * Не используем NavigationGuardWithThis<undefined>, т.к. она требует
 * `this: undefined` что неудобно для прямого вызова в тестах.
 */
type SimpleGuard = (
  to: RouteLocationNormalized,
  from: RouteLocationNormalized,
  next?: unknown,
) => GuardResult

export interface AuthGuardOptions {
  /** Имя login-роута. По умолчанию 'admin.login'. */
  loginRouteName?: string
  /** Имя роута 403-страницы. По умолчанию 'admin.forbidden'. */
  forbiddenRouteName?: string
  /**
   * Имя query-параметра для возврата на исходную страницу после login.
   * По умолчанию 'redirect'.
   */
  redirectQueryKey?: string
}

/**
 * Создаёт beforeEach guard. Pinia должна быть activeInstance к моменту
 * вызова guard'а (router.beforeEach срабатывает на каждой навигации,
 * включая первую — Pinia уже должна быть установлена).
 */
export function createAuthGuard(opts: AuthGuardOptions = {}): SimpleGuard {
  const loginRouteName = opts.loginRouteName ?? 'admin.login'
  const forbiddenRouteName = opts.forbiddenRouteName ?? 'admin.forbidden'
  const redirectQueryKey = opts.redirectQueryKey ?? 'redirect'

  return (to: RouteLocationNormalized) => {
    // Login-роут всегда доступен.
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

    // Залогиненный, но в процессе 2FA — нельзя ходить никуда кроме login.
    if (auth.isChallengePending) {
      return {
        name: loginRouteName,
        query: { [redirectQueryKey]: to.fullPath },
      }
    }

    // Permission-check. ANY-логика (хотя бы одно permission совпало).
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
  /** Шаблон заголовка. {title} — meta.title, {brand} — название бренда. */
  template?: string
  /** Имя бренда. */
  brand?: string
  /** Default title если в meta нет. */
  fallback?: string
}

/**
 * Создаёт afterEach hook, обновляющий document.title.
 *
 * Шаблон по умолчанию: '{title} · {brand}' если оба, иначе один из.
 */
export function createTitleGuard(
  opts: TitleGuardOptions = {},
): (to: RouteLocationNormalized, from?: RouteLocationNormalized, failure?: unknown) => void {
  const template = opts.template
  const brand = opts.brand ?? ''
  const fallback = opts.fallback ?? ''

  return (to) => {
    if (typeof document === 'undefined') return
    const t = (to.meta?.title as string | undefined) ?? fallback
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

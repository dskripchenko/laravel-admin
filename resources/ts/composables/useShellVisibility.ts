import { computed, type ComputedRef } from 'vue'
import type { RouteLocationNormalizedLoaded } from 'vue-router'

/**
 * Should this route be drawn inside the admin shell, and is a catch-all match
 * a real 404 yet?
 *
 * Extracted from AdminApp so the rule can be tested as itself. Inline in the
 * SFC it was untestable, and the bug it now guards shipped unnoticed:
 * reloading any screen showed a full-page «Page not found» first and the real
 * page a moment later.
 *
 * The cause is that dynamic routes exist only after the manifest arrives, so
 * during boot EVERY deep link resolves to the catch-all. A boot gate for that
 * flash already existed — but it lived inside the shell branch, and the shell
 * was dropped precisely for `admin.notFound`. The one route the gate was
 * written for went down the ungated path.
 *
 * Hence `settledNotFound`: a catch-all match counts as a 404 only once the app
 * is ready. Before that it is a boot artefact and the visitor should see the
 * ordinary skeleton, exactly as on any other deep link.
 */
export interface ShellVisibility {
  /** Catch-all match that survived boot — a genuinely unknown address. */
  settledNotFound: ComputedRef<boolean>
  /** Draw the page inside the shell (sidebar + topbar). */
  useShell: ComputedRef<boolean>
}

export function useShellVisibility(
  route: RouteLocationNormalizedLoaded,
  appReady: ComputedRef<boolean>,
): ShellVisibility {
  const settledNotFound = computed<boolean>(
    () => route.name === 'admin.notFound' && appReady.value,
  )

  const useShell = computed<boolean>(() => {
    if (route.meta?.fullscreen === true) return false
    if (route.meta?.kind === 'auth') return false
    if (settledNotFound.value) return false

    return true
  })

  return { settledNotFound, useShell }
}

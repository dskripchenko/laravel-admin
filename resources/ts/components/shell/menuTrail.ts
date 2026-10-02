/**
 * Which menu item the current page belongs to — one answer for the whole shell.
 *
 * The sidebar and the breadcrumbs used to decide this each on their own: the
 * sidebar lit up every item that matched the route, the breadcrumbs picked one.
 * With the same page reachable from two places (`/r/orders` under Shop and
 * again under Showcase › Navigation › Nested menu) the sidebar opened one
 * branch and the breadcrumbs named the other. Both now ask findMenuTrail().
 *
 * The rule, in order:
 *   1. the most specific match: an exact one (route name or url) beats a
 *      prefix one, and a longer prefix beats a shorter one;
 *   2. the canonical node: one that points at the page by its route name (what
 *      MenuNode::resource()/screen() produce) beats one that only repeats its
 *      url;
 *   3. the shallowest node: the page's home section rather than a shortcut
 *      to it from deep inside another branch;
 *   4. the first declared, in the menu's own order.
 */
import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'
import { useRoute } from 'vue-router'
import { useMenuStore, type MenuItem } from '../../stores/menu'

export interface RouteLike {
  name?: unknown
  path: string
  params: Record<string, unknown>
  meta: Record<string, unknown>
}

/** How well a menu item matches the route: 0 is no match, an exact one beats a prefix. */
export function menuMatchScore(item: MenuItem, route: RouteLike): number {
  const name = typeof route.name === 'string' ? route.name : ''
  if (item.routeName && name === item.routeName) return 10_000
  if (item.url && route.path === item.url) return 10_000
  if (item.routeName && name !== '') {
    const base = String(item.routeName).replace(/\.(list|index)$/, '')
    if (name.startsWith(base + '.')) return 1_000 + base.length
  }
  if (item.url && route.path.startsWith(item.url + '/')) return 1_000 + item.url.length
  return 0
}

/** Whether the item reaches the route through its route name, not a copied url. */
function matchesByRouteName(item: MenuItem, route: RouteLike): boolean {
  const name = typeof route.name === 'string' ? route.name : ''
  if (!item.routeName || name === '') return false
  if (name === item.routeName) return true
  const base = String(item.routeName).replace(/\.(list|index)$/, '')
  return name.startsWith(base + '.')
}

/**
 * The best-matching menu item with the chain of its parents, outermost first;
 * empty when no item matches. See the module's comment for the rule.
 */
export function findMenuTrail(items: MenuItem[], route: RouteLike): MenuItem[] {
  let best: MenuItem[] = []
  let bestScore = 0
  let bestCanonical = false
  const walk = (list: MenuItem[], parents: MenuItem[]): void => {
    for (const item of list) {
      const score = menuMatchScore(item, route)
      if (score > 0) {
        const canonical = matchesByRouteName(item, route)
        const depth = parents.length + 1
        // Strictly better only: on a full tie the earlier item stays.
        const better = score > bestScore
          || (score === bestScore && canonical && !bestCanonical)
          || (score === bestScore && canonical === bestCanonical && depth < best.length)
        if (better) {
          bestScore = score
          bestCanonical = canonical
          best = [...parents, item]
        }
      }
      if (item.children?.length) walk(item.children, [...parents, item])
    }
  }
  walk(items, [])
  return best
}

/**
 * Where a sidebar node sits: the keys from the root down to it.
 *
 * The nodes are matched against the trail by this path, not by object
 * identity: the menu store rebuilds its items whenever the permissions are
 * re-read, and the same key may appear in two branches (MenuNode::resource()
 * gives every copy of a resource the key `resource.{slug}`).
 */
export const MENU_NODE_PATH: InjectionKey<string[]> = Symbol('admin-menu-node-path')

/** Whether the node at `path` lies on the trail; `exact` — whether it is its last item. */
export function pathOnTrail(path: string[], trail: MenuItem[], exact = false): boolean {
  if (path.length === 0 || path.length > trail.length) return false
  if (exact && path.length !== trail.length) return false
  return path.every((key, i) => trail[i]?.key === key)
}

/** The active trail the sidebar shares with its nodes. */
export const ACTIVE_MENU_TRAIL: InjectionKey<ComputedRef<MenuItem[]>> = Symbol('admin-active-menu-trail')

/** The active trail over the whole visible menu, for the current route. */
export function useActiveMenuTrail(): ComputedRef<MenuItem[]> {
  const route = useRoute()
  const menu = useMenuStore()
  return computed(() => findMenuTrail(menu.visibleItems, route as unknown as RouteLike))
}

/** The trail provided by the sidebar, or null outside of one. */
export function injectActiveMenuTrail(): ComputedRef<MenuItem[]> | null {
  return inject(ACTIVE_MENU_TRAIL, null)
}

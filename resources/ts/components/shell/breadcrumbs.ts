/**
 * The top bar's breadcrumbs, derived from where the visitor is.
 *
 * The trail is the sidebar's: the menu item that matches the route — the very
 * one AdminSidebarNode marks active, through the shared findMenuTrail() —
 * preceded by its group and its parent items. A resource route adds what lies below the list: the record
 * (named by its title, name or label, else #id) and "Editing" or "Creating".
 * A page the menu does not hold is named by its own title.
 *
 * The last crumb is the current page and carries no link.
 */
import { computed, type ComputedRef } from 'vue'
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import type { MenuItem } from '../../stores/menu'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { useUserNavStore } from '../../stores/userNav'
import { trSafe as tr } from '../../stores/i18n'
import { findMenuTrail, menuMatchScore, useActiveMenuTrail, type RouteLike } from './menuTrail'
import { recordTitleOf, singularOf } from '../resource/resourceNoun'

export interface Crumb {
  label: string
  to?: RouteLocationRaw | null
  /** 'tab' — a tab open on the page (TabsLayout with persistInQuery()). */
  kind?: string | null
}

// The trail is shared with the sidebar: one rule for both (see menuTrail.ts).
export { findMenuTrail, menuMatchScore }

function itemTarget(item: MenuItem): RouteLocationRaw | null {
  if (item.routeName) return { name: item.routeName }
  if (item.url) return item.url
  return null
}

/**
 * A crumb's target with the query its page was last left with — the list's
 * filters, the open tab — so that a crumb leads back to the list as it was,
 * not to its bare first page.
 */
function withLastQuery(
  target: RouteLocationRaw | null,
  path: string | null,
  queryOf: (path: string) => Record<string, string | string[]> | null,
): RouteLocationRaw | null {
  if (target === null || path === null) return target
  const query = queryOf(path)
  if (query === null) return target
  if (typeof target === 'string') return { path: target, query }
  return { ...(target as Record<string, unknown>), query } as RouteLocationRaw
}

/**
 * A record's crumb: its title, name or label; without one "Author #12" when
 * the resource names its records (Resource::singularLabel()), else "#12".
 */
function recordLabel(record: Record<string, unknown>, id: string, singular: string | null): string {
  const own = recordTitleOf(record)
  if (own !== null) return own
  return singular !== null ? `${singular.charAt(0).toUpperCase()}${singular.slice(1)} #${id}` : `#${id}`
}

export function useBreadcrumbs(): ComputedRef<Crumb[]> {
  const route = useRoute()
  const activeTrail = useActiveMenuTrail()
  const manifest = useManifestStore()
  const form = useResourceFormStore()
  const userNav = useUserNavStore()
  const router = (() => {
    try {
      return useRouter()
    } catch {
      return null
    }
  })()

  /** The path a target leads to, for its remembered query. */
  const pathOf = (target: RouteLocationRaw | null): string | null => {
    if (target === null) return null
    if (typeof target === 'string') return target.split('?')[0] ?? target
    try {
      return router?.resolve(target).path ?? null
    } catch {
      return null
    }
  }
  const linkTo = (target: RouteLocationRaw | null): RouteLocationRaw | null =>
    withLastQuery(target, pathOf(target), userNav.queryOf)

  return computed<Crumb[]>(() => {
    const r = route as unknown as RouteLike
    const name = typeof r.name === 'string' ? r.name : ''
    const kind = r.meta.kind
    const slug = typeof r.meta.slug === 'string' ? r.meta.slug : ''
    const trail = activeTrail.value
    const crumbs: Crumb[] = []

    // 'compact' (admin.navigation.breadcrumbs): the area, the screen, its tab
    // and the record — the menu's groups, which lead nowhere, are left out.
    const compact = userNav.config.breadcrumbs === 'compact'
    const group = trail[0]?.group
    if (group && !trail[0]?.sectionOf && !compact) crumbs.push({ label: group })
    trail.forEach((item, idx) => {
      const target = itemTarget(item)
      const isGroup = target === null && (item.children?.length ?? 0) > 0
      if (compact && isGroup && idx > 0 && idx < trail.length - 1) return
      crumbs.push({ label: item.label, to: linkTo(target) })
    })

    const push = (crumb: Crumb): void => {
      // A page the menu item already names is not named twice.
      if (crumbs[crumbs.length - 1]?.label === crumb.label) {
        crumbs[crumbs.length - 1] = { ...crumbs[crumbs.length - 1], ...crumb }
        return
      }
      crumbs.push(crumb)
    }

    if (kind === 'resource' && slug !== '') {
      const index = `admin.resource.${slug}.index`
      const leaf = trail[trail.length - 1]
      const menuHoldsList = leaf !== undefined
        && (leaf.routeName === index || leaf.url === `/r/${slug}`)
      if (!menuHoldsList) {
        push({ label: manifest.getResource(slug)?.label ?? slug, to: linkTo({ name: index }) })
      }

      const id = typeof r.params.id === 'string' ? r.params.id : ''
      const singular = singularOf(manifest.getResource(slug))
      const sameRecord = form.slug === slug && String(form.recordId ?? '') === id
      // Resource::recordTitle() first, then the record's own title, name or label.
      const record: Record<string, unknown> = sameRecord
        ? { ...form.state, ...form.initial, ...(form.recordTitle ? { title: form.recordTitle } : {}) }
        : {}
      if (name.endsWith('.create')) {
        crumbs.push({ label: tr('Создание') })
      } else if (name.endsWith('.view') && id !== '') {
        crumbs.push({ label: recordLabel(record, id, singular) })
      } else if (name.endsWith('.edit') && id !== '') {
        crumbs.push({
          label: recordLabel(record, id, singular),
          to: { name: `admin.resource.${slug}.view`, params: { id } },
        })
        crumbs.push({ label: tr('Редактирование') })
      }
    } else if (typeof r.meta.title === 'string' && r.meta.title !== '') {
      const title = kind === 'screen' && slug !== ''
        ? (manifest.getScreen(slug)?.name ?? r.meta.title)
        : r.meta.title
      if (trail.length === 0 || menuMatchScore(trail[trail.length - 1]!, r) < 10_000) {
        push({ label: tr(title) })
      }
    }

    // The tabs open on the page, outer first: «Заявки › CRM › Не переданы».
    if (kind !== 'resource') {
      for (const tab of userNav.openTabs) crumbs.push({ label: tab.label, kind: 'tab' })
    }

    // The last crumb is the current page: no link.
    if (crumbs.length > 0) {
      const last = crumbs[crumbs.length - 1]!
      crumbs[crumbs.length - 1] = { label: last.label, ...(last.kind ? { kind: last.kind } : {}) }
    }
    return crumbs
  })
}

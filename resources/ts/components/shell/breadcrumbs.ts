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
import { useRoute, type RouteLocationRaw } from 'vue-router'
import type { MenuItem } from '../../stores/menu'
import { useManifestStore } from '../../stores/manifest'
import { useResourceFormStore } from '../../stores/resourceForm'
import { trSafe as tr } from '../../stores/i18n'
import { findMenuTrail, menuMatchScore, useActiveMenuTrail, type RouteLike } from './menuTrail'

export interface Crumb {
  label: string
  to?: RouteLocationRaw | null
}

// The trail is shared with the sidebar: one rule for both (see menuTrail.ts).
export { findMenuTrail, menuMatchScore }

function itemTarget(item: MenuItem): RouteLocationRaw | null {
  if (item.routeName) return { name: item.routeName }
  if (item.url) return item.url
  return null
}

function recordLabel(record: Record<string, unknown>, id: string): string {
  for (const key of ['title', 'name', 'label']) {
    const v = record[key]
    if (typeof v === 'string' && v.trim() !== '') return v
    if (typeof v === 'number') return String(v)
  }
  return `#${id}`
}

export function useBreadcrumbs(): ComputedRef<Crumb[]> {
  const route = useRoute()
  const activeTrail = useActiveMenuTrail()
  const manifest = useManifestStore()
  const form = useResourceFormStore()

  return computed<Crumb[]>(() => {
    const r = route as unknown as RouteLike
    const name = typeof r.name === 'string' ? r.name : ''
    const kind = r.meta.kind
    const slug = typeof r.meta.slug === 'string' ? r.meta.slug : ''
    const trail = activeTrail.value
    const crumbs: Crumb[] = []

    const group = trail[0]?.group
    if (group) crumbs.push({ label: group })
    for (const item of trail) crumbs.push({ label: item.label, to: itemTarget(item) })

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
        push({ label: manifest.getResource(slug)?.label ?? slug, to: { name: index } })
      }

      const id = typeof r.params.id === 'string' ? r.params.id : ''
      const sameRecord = form.slug === slug && String(form.recordId ?? '') === id
      // Resource::recordTitle() first, then the record's own title, name or label.
      const record: Record<string, unknown> = sameRecord
        ? { ...form.state, ...form.initial, ...(form.recordTitle ? { title: form.recordTitle } : {}) }
        : {}
      if (name.endsWith('.create')) {
        crumbs.push({ label: tr('Создание') })
      } else if (name.endsWith('.view') && id !== '') {
        crumbs.push({ label: recordLabel(record, id) })
      } else if (name.endsWith('.edit') && id !== '') {
        crumbs.push({
          label: recordLabel(record, id),
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

    // The last crumb is the current page: no link.
    if (crumbs.length > 0) {
      const last = crumbs[crumbs.length - 1]!
      crumbs[crumbs.length - 1] = { label: last.label }
    }
    return crumbs
  })
}

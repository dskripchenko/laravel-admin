import type { LayoutNode } from '../render/LayoutRenderer.vue'

/**
 * The status label in the header of a resource's form.
 *
 * The header printed the RAW value (`active`) beside a fully translated form,
 * while the select two lines below showed "Active". The labels arrive in the
 * manifest already translated, by `Localize::options` — there is nothing to
 * translate, one only has to look at them.
 *
 * It lives in a module of its own rather than inside the SFC so that a test
 * can reach it: inside the component the logic was uncheckable, which is how
 * the defect shipped.
 */

/** Finds a field by name in a tree of any depth: tabs → rows → fields. */
export function findFieldNode(
  nodes: LayoutNode[],
  name: string,
): Record<string, unknown> | null {
  for (const node of nodes) {
    const n = node as unknown as Record<string, unknown>
    if (n.kind === 'field' && n.name === name) return n

    const items = (n.items ?? n.children) as LayoutNode[] | undefined
    if (Array.isArray(items)) {
      const hit = findFieldNode(items, name)
      if (hit !== null) return hit
    }
  }

  return null
}

/**
 * The labels may come either as a `value => label` map or as a list of
 * `[{value, label}]` — both are supported, or some resources would be left
 * with the machine value.
 *
 * With no matching label the value itself is returned: a machine word in the
 * header beats emptiness, since it at least shows what state the record is in.
 */
export function resolveStatusLabel(
  nodes: LayoutNode[],
  value: string | null,
): string | null {
  if (value === null) return null

  const field = findFieldNode(nodes, 'status')

  // The labels sit in `attributes.options`, where the HasOptions trait keeps
  // them. The top-level `options` key IS present in the serialization but is
  // always empty: it reads a property the fields never fill in. The first
  // version looked there, honestly found nothing and fell back to the machine
  // value — the fix looked done and did not work.
  const attributes = field?.attributes as Record<string, unknown> | undefined
  const options = attributes?.options ?? field?.options

  if (Array.isArray(options)) {
    const hit = options.find(
      (o) => String((o as Record<string, unknown>)?.value) === value,
    ) as Record<string, unknown> | undefined
    if (typeof hit?.label === 'string') return hit.label
  } else if (options !== null && typeof options === 'object') {
    const hit = (options as Record<string, unknown>)[value]
    if (typeof hit === 'string') return hit
  }

  return value
}

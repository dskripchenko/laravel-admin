/**
 * The option trees TreeSelect and Cascader serialize:
 *   [{ value, label, children?: [...] }, ...]
 */

export type OptionValue = string | number

export interface OptionTreeItem {
  value: OptionValue
  label: string
  disabled?: boolean
  children?: OptionTreeItem[]
}

export interface FlatOption {
  value: OptionValue
  label: string
}

/** Keeps only well-formed items: the payload comes from the host's arrays. */
export function normalizeTree(raw: unknown): OptionTreeItem[] {
  if (!Array.isArray(raw)) return []
  const out: OptionTreeItem[] = []
  for (const item of raw) {
    if (item === null || typeof item !== 'object') continue
    const rec = item as Record<string, unknown>
    const value = rec.value ?? rec.key ?? rec.id
    if (typeof value !== 'string' && typeof value !== 'number') continue
    const node: OptionTreeItem = { value, label: String(rec.label ?? rec.name ?? value) }
    if (rec.disabled === true) node.disabled = true
    const children = normalizeTree(rec.children)
    if (children.length > 0) node.children = children
    out.push(node)
  }
  return out
}

/** The labels from the root down to the node with `value`; null when absent. */
export function findLabelPath(tree: OptionTreeItem[], value: unknown): string[] | null {
  for (const node of tree) {
    // Loose comparison: an id may come back as "5" from one side and 5 from the other.
    if (String(node.value) === String(value)) return [node.label]
    if (node.children) {
      const rest = findLabelPath(node.children, value)
      if (rest) return [node.label, ...rest]
    }
  }
  return null
}

/** The labels of a cascader path: one value per level. */
export function cascaderLabels(tree: OptionTreeItem[], path: unknown[]): string[] {
  const labels: string[] = []
  let level: OptionTreeItem[] | undefined = tree
  for (const v of path) {
    const node: OptionTreeItem | undefined = level?.find((n) => String(n.value) === String(v))
    if (!node) {
      labels.push(String(v))
      level = undefined
      continue
    }
    labels.push(node.label)
    level = node.children
  }
  return labels
}

/** A flat options list ([{value, label}]) — the label of a value, or the value itself. */
export function optionLabel(options: unknown, value: unknown): string {
  if (Array.isArray(options)) {
    for (const o of options) {
      if (o && typeof o === 'object' && String((o as FlatOption).value) === String(value)) {
        return String((o as FlatOption).label)
      }
    }
  } else if (options && typeof options === 'object') {
    // The {value: label} map form some hosts still send.
    const label = (options as Record<string, unknown>)[String(value)]
    if (label !== undefined) return String(label)
  }
  return String(value)
}

/**
 * The arithmetic of the permission matrix (Field\PermissionMatrix): which
 * keys a group holds, its three-state check, the search over the labels and
 * the keys.
 */

export interface MatrixItem {
  key: string
  label: string
  hint?: string | null
  /** The key of the second column for this row (ПДн без маски), when there is one. */
  extra?: string | null
}

export interface MatrixGroup {
  label: string
  items: MatrixItem[]
}

export type CheckState = 'none' | 'some' | 'all'

/** Every key of the rows — the second column's among them. */
export function keysOf(items: ReadonlyArray<MatrixItem>, withExtra = true): string[] {
  const out: string[] = []
  for (const item of items) {
    out.push(item.key)
    if (withExtra && item.extra) out.push(item.extra)
  }
  return out
}

/** Checked of the rows' main keys: none, some or all. */
export function stateOf(keys: ReadonlyArray<string>, selected: ReadonlySet<string>): CheckState {
  if (keys.length === 0) return 'none'
  let n = 0
  for (const k of keys) if (selected.has(k)) n++
  return n === 0 ? 'none' : n === keys.length ? 'all' : 'some'
}

/** How many of the keys are checked. */
export function countIn(keys: ReadonlyArray<string>, selected: ReadonlySet<string>): number {
  let n = 0
  for (const k of keys) if (selected.has(k)) n++
  return n
}

/**
 * The groups as the search and «Только выбранные» leave them: the rows whose
 * label, hint or key holds the query (a group whose own label does keeps
 * every row), the groups left without rows dropped.
 */
export function filterGroups(
  groups: ReadonlyArray<MatrixGroup>,
  query: string,
  onlySelected: boolean,
  selected: ReadonlySet<string>,
): MatrixGroup[] {
  const q = query.trim().toLocaleLowerCase()
  const out: MatrixGroup[] = []
  for (const group of groups) {
    const groupHit = q !== '' && group.label.toLocaleLowerCase().includes(q)
    const items = group.items.filter((item) => {
      if (onlySelected && !selected.has(item.key) && !(item.extra && selected.has(item.extra))) return false
      if (q === '' || groupHit) return true
      return item.label.toLocaleLowerCase().includes(q)
        || item.key.toLocaleLowerCase().includes(q)
        || (item.hint ?? '').toLocaleLowerCase().includes(q)
        || (item.extra ?? '').toLocaleLowerCase().includes(q)
    })
    if (items.length > 0) out.push({ label: group.label, items })
  }
  return out
}

/** The selection with the keys turned on or off; the order of the rest is kept. */
export function toggleKeys(current: ReadonlyArray<string>, keys: ReadonlyArray<string>, on: boolean): string[] {
  if (on) {
    const have = new Set(current)
    return [...current, ...keys.filter((k) => !have.has(k))]
  }
  const drop = new Set(keys)
  return current.filter((k) => !drop.has(k))
}

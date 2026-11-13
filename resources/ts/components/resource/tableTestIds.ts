/**
 * The test ids of a resource table's rows and cells (see docs/en/testing.md,
 * "data-testid for end-to-end tests"). The kit's table draws the rows itself and takes no
 * attributes for them, so they are put on its elements after each render:
 *
 *   - a row — `resource-row-{key}` (the record's key, `data-row-key` too);
 *   - a cell — `cell-{column}`; the actions column — `row-actions`, the drag
 *     handle's — `row-drag`.
 *
 * The data rows are the table's `tr.uid-table__row`, in the order of the
 * data; a group's heading and an expanded row are other rows and are skipped.
 */
import { testIdOf } from '../../composables/testId'

export interface TableTestIdOptions {
  rows: Array<Record<string, unknown>>
  keyOf: (row: Record<string, unknown>) => string | number
  /** The keys of the columns in the order drawn (the selection column is not one). */
  columns: string[]
  /** Column keys named otherwise: `{__row_actions__: 'row-actions'}`. */
  names?: Record<string, string>
}

export function markTableRows(container: HTMLElement | null | undefined, options: TableTestIdOptions): void {
  if (!container) return
  const trs = [...container.querySelectorAll<HTMLTableRowElement>('tbody > tr')].filter((tr) =>
    tr.classList.contains('uid-table__row'),
  )
  trs.forEach((tr, i) => {
    const row = options.rows[i]
    if (!row) return
    const key = String(options.keyOf(row))
    tr.setAttribute('data-testid', testIdOf('resource-row', key))
    tr.setAttribute('data-row-key', key)
    const cells = [...tr.children].filter((td) => !td.classList.contains('uid-table__td--select'))
    cells.forEach((td, c) => {
      const column = options.columns[c]
      if (column === undefined) return
      td.setAttribute('data-testid', options.names?.[column] ?? testIdOf('cell', column))
    })
  })
}

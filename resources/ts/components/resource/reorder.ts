/**
 * The drag-and-drop reordering of a resource list — the request the list page
 * sends to `POST /{slug}/reorder` and the rules around it, kept apart from
 * the page so the contract with ResourceController::reorder() is tested on
 * its own.
 *
 * The body is `{ids, offset}`: the visible rows in their new order and the
 * index of the page's first row. The server hands those rows the positions
 * they already hold, smallest first, and numbers them from `offset` only
 * when those positions are unusable — so a page of a paginated or filtered
 * list reorders within its own slots.
 */

export interface ReorderPayload {
  ids: Array<string | number>
  offset: number
}

export interface ReorderResponse {
  count?: number
  /** The new positions by primary key. */
  positions?: Record<string, number>
}

export function buildReorderPayload(
  ids: Array<string | number>,
  page: number,
  perPage: number,
): ReorderPayload {
  const safePage = Number.isFinite(page) && page > 1 ? Math.floor(page) : 1
  const safePerPage = Number.isFinite(perPage) && perPage > 0 ? Math.floor(perPage) : 0
  return { ids, offset: (safePage - 1) * safePerPage }
}

/**
 * Whether dragging may reorder the rows: only in the manual order — no sort,
 * or the reorder column ascending. Under any other sort the visible sequence
 * is not the stored one, and a drop would scramble it.
 */
export function canDragReorder(
  reorderable: boolean,
  reorderColumn: string | null | undefined,
  sortKey: string | null,
  sortDirection: 'asc' | 'desc' | null,
): boolean {
  if (!reorderable) return false
  if (sortKey === null || sortDirection === null) return true
  return sortKey === (reorderColumn ?? 'position') && sortDirection === 'asc'
}

/**
 * The rows with the positions the server answered, written into the reorder
 * column where the rows carry it. Rows without a new position stay as they are.
 */
export function applyPositions(
  rows: Array<Record<string, unknown>>,
  positions: Record<string, number> | undefined,
  column: string | null | undefined,
  rowId: (row: Record<string, unknown>) => string | number,
): Array<Record<string, unknown>> {
  if (!positions || !column) return rows
  return rows.map((row) => {
    const next = positions[String(rowId(row))]
    return typeof next === 'number' && column in row ? { ...row, [column]: next } : row
  })
}

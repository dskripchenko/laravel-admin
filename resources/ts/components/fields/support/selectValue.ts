import type { SelectValue } from '@dskripchenko/ui'

/**
 * The value of a single-value UidSelect. Since kit 1.5 its update event is
 * typed for the multiple mode too (`SelectValue | SelectValue[] | null`),
 * whatever `multiple` is; a select without `multiple` never emits a list, so
 * a list is read as its first value.
 */
export function singleSelectValue(next: SelectValue | SelectValue[] | null | undefined): SelectValue | null {
  if (Array.isArray(next)) return next[0] ?? null
  return next ?? null
}

/**
 * What a save or an action in the record drawer asks of the list under it:
 * with nextAfterAction the list moves on to the next record, unless the
 * action keeps its record open (Action::keepsRecord() on the backend, or
 * `keep_record` in the action's answer).
 */
import type { AdminAction } from '../../composables/useActionRunner'

/** What a save or an action in the drawer asks of the list. */
export interface DrawerOutcome {
  /** The record stays open: the list does not move on to the next one. */
  keep?: boolean
}

/**
 * Whether an action keeps its record open: `keep_record` of its answer when
 * the method set one, otherwise the action's own keepsRecord().
 */
export function actionKeepsRecord(action: AdminAction | null, result: unknown): boolean {
  const answered = (result as { keep_record?: unknown } | null | undefined)?.keep_record
  if (typeof answered === 'boolean') return answered
  return action?.attributes.keepsRecord === true
}

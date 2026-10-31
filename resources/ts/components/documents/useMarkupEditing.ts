/**
 * The editing of a markup document on a screen — the kanban and calendar
 * layouts (see the backend's MarkupLayout).
 *
 * A change is shown at once and sent to the screen method `saveMethod` with
 * the new markup, through the screen context's `call` — the screen's
 * runMethod, so the session, CSRF, layers and the server's permission checks
 * apply. A refusal rolls the document back to the markup before the change
 * and shows the reason in a toast. The lock button calls `lockMethod` with
 * the new state the same way.
 *
 * Outside a screen page (no `call`) the document is read-only.
 */
import { computed, nextTick, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useScreenContext } from '../render/screenContext'
import { useStateReader } from './useDocumentSource'
import { adminToast, toastAlerts, toastError } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'
import type { ScreenMethodResult } from '../../stores/screen'

export interface MarkupEditingProps {
  editable?: boolean
  saveMethod?: string | null
  lockable?: boolean
  lockMethod?: string | null
  locked?: boolean
  lockedSource?: string | null
}

export interface MarkupEditing {
  /** The markup shown: the last change, else the source's text. */
  markup: ComputedRef<string>
  canEdit: ComputedRef<boolean>
  canLock: ComputedRef<boolean>
  isLocked: ComputedRef<boolean>
  saving: Ref<boolean>
  onChange: (next: string) => Promise<void>
  onLock: (next: boolean) => Promise<void>
}

function report(res: ScreenMethodResult): void {
  if (res.message) adminToast.success(res.message)
  toastAlerts(res.alerts, res.message)
}

export function useMarkupEditing(
  props: Readonly<MarkupEditingProps>,
  text: Readonly<Ref<string | null>>,
): MarkupEditing {
  const call = useScreenContext()?.call
  const read = useStateReader()

  /** The markup after the user's changes; a new text from the source replaces it. */
  const changed = ref<string | null>(null)
  watch(text, () => {
    changed.value = null
  })
  const markup = computed(() => changed.value ?? text.value ?? '')

  const lockedFromSource = computed(() =>
    props.lockedSource ? Boolean(read(props.lockedSource)) : props.locked === true,
  )
  const lockedNow = ref<boolean | null>(null)
  watch(lockedFromSource, () => {
    lockedNow.value = null
  })
  const isLocked = computed(() => lockedNow.value ?? lockedFromSource.value)

  const canEdit = computed(() => props.editable === true && !!props.saveMethod && !!call)
  const canLock = computed(() => props.lockable === true && !!props.lockMethod && !!call)
  const saving = ref(false)

  async function onChange(next: string): Promise<void> {
    const method = props.saveMethod
    if (!call || !method || !canEdit.value) return
    const previous = markup.value
    if (next === previous) return
    changed.value = next
    // Let the component see the new markup before a refusal may restore the old one.
    await nextTick()
    saving.value = true
    try {
      report(await call(method, [next]))
    } catch (err) {
      // A later change has its own request; only the newest one is rolled back.
      if (changed.value === next) changed.value = previous
      toastError(err, tr('Не удалось сохранить изменения'))
    } finally {
      saving.value = false
    }
  }

  async function onLock(next: boolean): Promise<void> {
    const method = props.lockMethod
    if (!call || !method || !canLock.value) return
    const previous = isLocked.value
    lockedNow.value = next
    await nextTick()
    try {
      report(await call(method, [next]))
    } catch (err) {
      if (lockedNow.value === next) lockedNow.value = previous
      toastError(err, tr('Не удалось изменить блокировку'))
    }
  }

  return { markup, canEdit, canLock, isLocked, saving, onChange, onLock }
}

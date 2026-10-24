import { computed, type ComputedRef } from 'vue'
import type { UidMarkdownCalloutKind } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'

/**
 * Callout titles in the panel's language, through its own dictionaries —
 * which cover more languages than the kit's string bags.
 */
export function useCalloutTitles(): ComputedRef<Record<UidMarkdownCalloutKind, string>> {
  return computed(() => ({
    note: tr('Примечание'),
    tip: tr('Совет'),
    important: tr('Важно'),
    warning: tr('Внимание'),
    caution: tr('Осторожно'),
  }))
}

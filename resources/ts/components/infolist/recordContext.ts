/**
 * The composable behind the read-only record context an infolist passes down.
 *
 * The container — ResourceViewPage — calls `provideRecord(record)`, and the
 * entry components call `useRecord()` to read the values.
 */

import { inject, provide, type InjectionKey } from 'vue'

const RecordKey: InjectionKey<Record<string, unknown>> = Symbol('admin.infolist-record')

export function provideRecord(record: Record<string, unknown>): void {
  provide(RecordKey, record)
}

export function useRecord(): Record<string, unknown> {
  const r = inject(RecordKey)
  if (!r) {
    throw new Error('useRecord() called outside of provideRecord() scope')
  }
  return r
}

export function tryUseRecord(): Record<string, unknown> | null {
  return inject(RecordKey, null) as Record<string, unknown> | null
}

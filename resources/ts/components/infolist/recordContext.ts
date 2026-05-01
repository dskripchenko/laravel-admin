/**
 * Composable для read-only record-контекста, передаваемого Infolist'ом.
 *
 * Контейнер (ResourceViewPage) вызывает `provideRecord(record)`, entry-
 * компоненты — `useRecord()` для чтения значения.
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

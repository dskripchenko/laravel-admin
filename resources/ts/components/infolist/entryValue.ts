/**
 * The value an entry displays: the explicit `value` prop when the node carries
 * one, otherwise `record[name]` from the provided record.
 */
import { computed, type ComputedRef } from 'vue'
import { tryUseRecord } from './recordContext'

export function useEntryValue(props: { name?: string; value?: unknown }): ComputedRef<unknown> {
  const record = tryUseRecord()
  return computed<unknown>(() => {
    if (props.value !== undefined) return props.value
    return record && props.name ? record[props.name] : undefined
  })
}

export const isEmpty = (v: unknown): boolean =>
  v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)

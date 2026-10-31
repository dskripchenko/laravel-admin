/**
 * Where a document layout's text comes from (see the backend's
 * DocumentLayout): the content given inline, a key of the screen's state —
 * the form's working copy, else the infolist record, followed as it changes —
 * or an address of the same site, fetched with the user's session once per
 * address.
 */
import { computed, ref, shallowRef, watch, type ComputedRef, type Ref, type ShallowRef } from 'vue'
import { tryUseFormState } from '../render/formState'
import { tryUseRecord } from '../infolist/recordContext'
import { trSafe as tr } from '../../stores/i18n'

export interface DocumentSourceProps {
  content?: string | null
  source?: string | null
  url?: string | null
}

export interface DocumentSource {
  /** The text; null while it loads or after a failed load. */
  text: ComputedRef<string | null>
  loading: Ref<boolean>
  /** The text is there and blank. */
  empty: ComputedRef<boolean>
  /** Why the address could not be read. */
  error: Ref<string | null>
}

/** Fetched texts by address: several layouts with one address share a request. */
const fetched = new Map<string, Promise<string>>()

/** @internal Forgets the fetched texts, for tests. */
export function clearDocumentCache(): void {
  fetched.clear()
}

/** A stored value as text: a string as is, an object as JSON, nothing as ''. */
export function toText(value: unknown): string {
  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

/** The text at an address of the panel's own site. */
export function fetchDocument(url: string): Promise<string> {
  let target: URL
  try {
    target = new URL(url, window.location.href)
  } catch {
    return Promise.reject(new Error(tr('Неверный адрес документа')))
  }
  if (target.origin !== window.location.origin) {
    return Promise.reject(new Error(tr('Документ можно загрузить только с этого же сайта')))
  }
  const key = target.href
  let request = fetched.get(key)
  if (!request) {
    request = fetch(key, { credentials: 'same-origin' }).then(async (res) => {
      if (!res.ok) throw new Error(`${tr('Не удалось загрузить документ')}: HTTP ${res.status}`)
      return res.text()
    })
    fetched.set(key, request)
    // A failed request is tried again next time.
    request.catch(() => fetched.delete(key))
  }
  return request
}

/** A key of the screen's state: the form's working copy, else the record. */
export function useStateReader(): (key: string) => unknown {
  const form = tryUseFormState()
  const record = tryUseRecord()
  return (key: string): unknown => {
    const fromForm = form?.getField(key)
    if (fromForm !== undefined) return fromForm
    return record ? record[key] : undefined
  }
}

export function useDocumentSource(props: Readonly<DocumentSourceProps>): DocumentSource {
  const read = useStateReader()
  const loaded = shallowRef<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  watch(
    () => props.url,
    (url) => {
      loaded.value = null
      error.value = null
      if (!url) {
        loading.value = false
        return
      }
      loading.value = true
      fetchDocument(url).then(
        (text) => {
          if (props.url !== url) return
          loaded.value = text
          loading.value = false
        },
        (err: unknown) => {
          if (props.url !== url) return
          error.value = err instanceof Error ? err.message : String(err)
          loading.value = false
        },
      )
    },
    { immediate: true },
  )

  const text = computed<string | null>(() => {
    if (props.url) return loaded.value
    if (props.source) return toText(read(props.source))
    return props.content ?? ''
  })

  const empty = computed(() => text.value !== null && text.value.trim() === '')

  return { text, loading, error, empty }
}

/**
 * A package for a component: null until it has loaded, with the error of a
 * failed download.
 */
export function useLazyModule<T>(load: () => Promise<T>): { module: ShallowRef<T | null>; failed: Ref<boolean> } {
  const module = shallowRef<T | null>(null)
  const failed = ref(false)
  load().then(
    (m) => {
      module.value = m
    },
    () => {
      failed.value = true
    },
  )
  return { module, failed }
}

/** A CSS length: 480 → '480px'; strings pass through; nothing — undefined. */
export function cssHeight(value: number | string | null | undefined): string | undefined {
  if (value === null || value === undefined || value === '') return undefined
  return typeof value === 'number' ? `${value}px` : value
}

/** A unique id prefix per drawing, so ids inside SVGs do not collide on a page. */
let sequence = 0
export function nextIdPrefix(kind: string): string {
  return `admin-${kind}-l${++sequence}`
}

/** An error's message. */
export function messageOf(error: unknown): string {
  return error instanceof Error ? error.message : String(error)
}

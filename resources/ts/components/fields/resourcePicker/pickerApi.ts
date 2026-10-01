/**
 * The data side of the resource picker (the backend's Field\ResourcePicker).
 *
 * Everything goes through the target resource's own search endpoint,
 * POST /{resource}/search, so the picker sees exactly what the resource's list
 * shows and is gated by the same permission. `picker: true` makes the backend
 * add `_picker` — {id, title, subtitle, preview} from Resource::pickerItem() —
 * to every row; `ids` narrows the search to the records a field holds.
 */
import { getAdminClient } from '../../../stores/registry'

export type PickerKey = string | number

export interface PickerItem {
  id: PickerKey
  title: string
  subtitle: string | null
  preview: string | null
}

export interface PickerUpload {
  url: string
  permission?: string | null
  fileField?: string
  responseKey?: string | null
  data?: Record<string, string | number | boolean>
  accept?: string | null
}

export interface PickerPage {
  items: PickerItem[]
  total: number
  page: number
  lastPage: number
}

export interface PickerQuery {
  page?: number
  perPage?: number
  q?: string
  filters?: Record<string, unknown>
}

interface SearchResponse {
  data: Array<Record<string, unknown>>
  meta: { total?: number; page?: number; last_page?: number }
}

/** The backend caps per_page; the ids are fetched in pages of this size. */
const MAX_PER_PAGE = 100

const isKey = (v: unknown): v is PickerKey =>
  typeof v === 'number' || (typeof v === 'string' && v !== '')

/** Two keys are the same record whether they came as 7 or as '7'. */
export const sameKey = (a: PickerKey, b: PickerKey): boolean => String(a) === String(b)

/** The field's value as an ordered list of keys, whatever shape it arrived in. */
export function keysOf(value: unknown): PickerKey[] {
  if (Array.isArray(value)) return value.filter(isKey)
  return isKey(value) ? [value] : []
}

/**
 * A search row as a picker item. `_picker` is what the backend sends; the
 * fallback covers a row without it — a host endpoint, say.
 */
export function toPickerItem(row: Record<string, unknown>): PickerItem | null {
  const own = row._picker as Partial<PickerItem> | undefined
  const id = own?.id ?? row.id
  if (!isKey(id)) return null
  const title = own?.title ?? row.title ?? row.name ?? row.label ?? `#${id}`
  return {
    id,
    title: String(title),
    subtitle: typeof own?.subtitle === 'string' ? own.subtitle : null,
    preview: typeof own?.preview === 'string' && own.preview !== '' ? own.preview : null,
  }
}

/** Filter values the way the resource index sends them: lists joined by commas, empties dropped. */
function filterParams(filters: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(filters)) {
    if (v === null || v === undefined || v === '') continue
    if (Array.isArray(v) && v.length === 0) continue
    out[k] = Array.isArray(v) ? v.join(',') : v
  }
  return out
}

export async function searchPicker(resource: string, query: PickerQuery = {}): Promise<PickerPage> {
  const body: Record<string, unknown> = {
    picker: true,
    page: query.page ?? 1,
    per_page: Math.min(query.perPage ?? 24, MAX_PER_PAGE),
  }
  if (query.q) body.q = query.q
  const filters = filterParams(query.filters ?? {})
  if (Object.keys(filters).length > 0) body.filters = filters

  const res = await getAdminClient().post<SearchResponse>(`/${resource}/search`, body)
  const items = (res.data ?? [])
    .map(toPickerItem)
    .filter((i): i is PickerItem => i !== null)
  return {
    items,
    total: Number(res.meta?.total ?? items.length),
    page: Number(res.meta?.page ?? 1),
    lastPage: Number(res.meta?.last_page ?? 1),
  }
}

/** The records behind the given keys. A key the resource no longer lists is simply absent. */
export async function fetchPickerItems(resource: string, keys: PickerKey[]): Promise<PickerItem[]> {
  const out: PickerItem[] = []
  for (let i = 0; i < keys.length; i += MAX_PER_PAGE) {
    const chunk = keys.slice(i, i + MAX_PER_PAGE)
    const res = await getAdminClient().post<SearchResponse>(`/${resource}/search`, {
      picker: true,
      ids: chunk,
      page: 1,
      per_page: chunk.length,
    })
    for (const row of res.data ?? []) {
      const item = toPickerItem(row)
      if (item) out.push(item)
    }
  }
  return out
}

/**
 * Uploads a file to the picker's upload endpoint and returns the new record's
 * key: the response is the record, or holds it under `responseKey`.
 */
export async function uploadForPicker(upload: PickerUpload, file: File): Promise<PickerKey | null> {
  const fd = new FormData()
  fd.append(upload.fileField ?? 'file', file)
  for (const [k, v] of Object.entries(upload.data ?? {})) {
    fd.append(k, String(v))
  }
  const res = await getAdminClient().post<Record<string, unknown>>(upload.url, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  const record = upload.responseKey ? res?.[upload.responseKey] : res
  const id = (record as Record<string, unknown> | null | undefined)?.id
  return isKey(id) ? id : null
}

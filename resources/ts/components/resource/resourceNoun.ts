/**
 * The phrases that name a resource's records: page titles, confirmations,
 * toasts and empty states.
 *
 * The manifest's `label` is plural ("Authors") and reads wrong inside a
 * sentence about one record. `singular_label` (Resource::singularLabel()) is
 * the name of one record as it reads mid-sentence ("author"), or null when the
 * resource has none in this locale.
 *
 * Every phrase is a Russian source string translated through the JSON
 * dictionary. A translation may use placeholders its source does not: Russian
 * would need the noun in the accusative, which a nominative singular cannot
 * give, so its source says "delete this record", while the English
 * translation says "Delete this :singular?". Every phrase therefore receives
 * the whole set:
 *
 *   :singular  the record's name, or the translated word "record" without one
 *   :Singular  the same with a capital letter, for the start of a sentence
 *   :label     the resource label as the manifest gives it
 *   :plural    the label lowered for the middle of a sentence
 */
import type { ManifestResourceMeta } from '../../stores/manifest'
import { tRaw, trSafe as tr } from '../../stores/i18n'

type Meta = Pick<ManifestResourceMeta, 'label'> & { singular_label?: string | null }

/**
 * Lowers a caption for the middle of a sentence. A word in capitals ("API",
 * "URL") is an acronym and keeps its case.
 */
export function nounCase(text: string): string {
  return text
    .split(' ')
    .map((word) => (word.length > 1 && word === word.toUpperCase() ? word : word.toLowerCase()))
    .join(' ')
}

function upperFirst(text: string): string {
  return text === '' ? text : text.charAt(0).toUpperCase() + text.slice(1)
}

/** The resource's singular in the current locale, or null without one. */
export function singularOf(meta: Meta | null | undefined): string | null {
  const s = meta?.singular_label
  return typeof s === 'string' && s.trim() !== '' ? s : null
}

/** The placeholder set every phrase receives; see the header. */
export function nounParams(meta: Meta | null | undefined, slug: string): Record<string, string> {
  const label = meta?.label ?? slug
  const singular = singularOf(meta) ?? tr('запись')
  return {
    singular,
    Singular: upperFirst(singular),
    label,
    plural: nounCase(label),
  }
}

/** The create page's title: "Create author", or "New record: Authors". */
export function createTitle(meta: Meta | null | undefined, slug: string): string {
  const params = nounParams(meta, slug)
  return singularOf(meta) !== null
    ? tRaw('Создать: :singular', params)
    : tRaw('Новая запись: :label', params)
}

/**
 * The name of a record without a title: "Author #12", or the older
 * "Authors: record #12" when the resource has no singular.
 */
export function recordFallbackTitle(meta: Meta | null | undefined, slug: string, id: string | number): string {
  const params = nounParams(meta, slug)
  return singularOf(meta) !== null
    ? `${params.Singular} #${id}`
    : `${params.label}: ${tRaw('запись #:id', { id })}`
}

/**
 * The edit page's title: "Edit author: Ivan Petrov" (the Russian source names
 * only the record), "Edit author #12" without a record title.
 */
export function editTitle(
  meta: Meta | null | undefined,
  slug: string,
  id: string | number,
  recordTitle: string | null,
): string {
  const params = nounParams(meta, slug)
  if (recordTitle !== null && recordTitle !== '') {
    return tRaw('Редактирование: :title', { ...params, title: recordTitle })
  }
  return singularOf(meta) !== null
    ? tRaw('Редактирование: :singular #:id', { ...params, id })
    : recordFallbackTitle(meta, slug, id)
}

/**
 * The delete confirmation: "Delete author “Ivan Petrov”?" when the record has
 * a title, "Delete this author?" otherwise.
 */
export function deleteConfirmText(meta: Meta | null | undefined, slug: string, recordTitle?: string | null): string {
  const params = nounParams(meta, slug)
  return recordTitle
    ? tRaw('Удалить «:title»?', { ...params, title: recordTitle })
    : tRaw('Удалить эту запись?', params)
}

/** The toast after a create: "Author created." */
export function createdToast(meta: Meta | null | undefined, slug: string): string {
  return tRaw('Новая запись создана.', nounParams(meta, slug))
}

/** The toast after a delete: "Author deleted." */
export function deletedToast(meta: Meta | null | undefined, slug: string): string {
  return tRaw('Запись успешно удалена.', nounParams(meta, slug))
}

/**
 * The empty state's description: "No authors yet. Create the first author." or,
 * for who may not create, "No authors yet."
 */
export function emptyDescription(meta: Meta | null | undefined, slug: string, canCreate: boolean): string {
  const params = nounParams(meta, slug)
  return canCreate
    ? tRaw('Пока нет ни одной записи — создайте первую.', params)
    : tRaw('В этом разделе пока нет записей.', params)
}

/** The create button's tooltip; the same words as the create page's title. */
export function createTooltip(meta: Meta | null | undefined, slug: string): string {
  return createTitle(meta, slug)
}

/**
 * Picks a record's title from its data: Resource::recordTitle() first, then the
 * record's own title, name or label.
 */
export function recordTitleOf(record: Record<string, unknown> | null | undefined): string | null {
  if (!record) return null
  for (const key of ['title', 'name', 'label']) {
    const v = record[key]
    if (typeof v === 'string' && v.trim() !== '') return v
    if (typeof v === 'number') return String(v)
  }
  return null
}

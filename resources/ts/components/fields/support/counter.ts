/**
 * The hint of a field with a character counter (Field::counter()):
 * «27 из 120 символов» after the field's own hint, or «27 символов» without
 * a limit. A soft limit (Field::counter($max, soft: true)) caps nothing and
 * only says when the text runs past the recommended length.
 */
import { tRaw } from '../../../stores/i18n'

export function counterHint(
  help: string | null,
  value: string,
  counter: boolean,
  max: number | null,
  softLimit: number | null = null,
): string | undefined {
  if (!counter) return help ?? undefined
  const n = [...value].length
  const limit = max ?? softLimit
  let count = limit ? tRaw(':n из :max символов', { n, max: limit }) : tRaw('Символов: :n', { n })
  if (!max && softLimit && n > softLimit) count = `${count} — ${tRaw('длиннее рекомендуемого')}`
  return help ? `${help} · ${count}` : count
}

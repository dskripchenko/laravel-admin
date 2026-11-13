/**
 * The hint of a field with a character counter (Field::counter()):
 * «27 из 120 символов» after the field's own hint, or «27 символов» without
 * a limit.
 */
import { tRaw } from '../../../stores/i18n'

export function counterHint(help: string | null, value: string, counter: boolean, max: number | null): string | undefined {
  if (!counter) return help ?? undefined
  const n = [...value].length
  const count = max ? tRaw(':n из :max символов', { n, max }) : tRaw('Символов: :n', { n })
  return help ? `${help} · ${count}` : count
}

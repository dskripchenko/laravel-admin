/**
 * Colours a widget receives from the backend — a gauge zone, a chart
 * dataset — as names: the UI kit's tones (success, warning, danger, info,
 * primary, neutral) or the plain colour words the docs use (green, amber,
 * red…). Both map onto the kit's tokens, so a zone follows the theme, dark
 * mode included. Anything else — #hex, rgb(), hsl(), var(--…) — is a CSS
 * colour already and passes through as it is.
 */
const TONE_TOKENS: Readonly<Record<string, string>> = {
  primary: 'var(--uid-accent)',
  accent: 'var(--uid-accent)',
  teal: 'var(--uid-accent)',
  success: 'var(--uid-color-success)',
  positive: 'var(--uid-color-success)',
  green: 'var(--uid-color-success)',
  emerald: 'var(--uid-color-success)',
  warning: 'var(--uid-color-warning)',
  amber: 'var(--uid-color-warning)',
  yellow: 'var(--uid-color-warning)',
  orange: 'var(--uid-color-warning)',
  danger: 'var(--uid-color-danger)',
  negative: 'var(--uid-color-danger)',
  error: 'var(--uid-color-danger)',
  red: 'var(--uid-color-danger)',
  rose: 'var(--uid-color-danger)',
  info: 'var(--uid-color-info)',
  blue: 'var(--uid-color-info)',
  neutral: 'var(--uid-color-zinc-400)',
  default: 'var(--uid-color-zinc-400)',
  gray: 'var(--uid-color-zinc-400)',
  grey: 'var(--uid-color-zinc-400)',
  zinc: 'var(--uid-color-zinc-400)',
}

/** The tone names toneColor() knows, for docs and tests. */
export const TONE_NAMES: readonly string[] = Object.keys(TONE_TOKENS)

/** A tone or colour name as a kit token; a CSS colour as it is; '' for nothing. */
export function toneColor(color: string | null | undefined): string {
  if (typeof color !== 'string') return ''
  const name = color.trim()
  if (name === '') return ''
  return TONE_TOKENS[name.toLowerCase()] ?? name
}

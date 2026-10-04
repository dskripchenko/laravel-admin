/**
 * The one tone vocabulary of the panel.
 *
 * A backend names a colour wherever it hands the SPA something to tint — a
 * stat card, a table badge, an infolist badge, a chart dataset, a gauge zone,
 * a heatmap — and it used to matter which of those it was: a badge took
 * `amber` and `gray`, a stat card did not. Every renderer now reads the same
 * names through resolveTone():
 *
 *   primary  primary, accent, teal
 *   success  success, positive, green, emerald, lime
 *   warning  warning, amber, yellow, orange
 *   danger   danger, negative, error, red, rose
 *   info     info, blue, sky, cyan, indigo
 *   neutral  neutral, default, gray, grey, zinc, slate, muted, secondary
 *
 * Names are case-insensitive. Each renderer then maps the six tones onto what
 * its kit component can draw — see the helpers below.
 */
export type PanelTone = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const TONE_ALIASES: Readonly<Record<string, PanelTone>> = {
  primary: 'primary',
  accent: 'primary',
  teal: 'primary',
  success: 'success',
  positive: 'success',
  green: 'success',
  emerald: 'success',
  lime: 'success',
  warning: 'warning',
  amber: 'warning',
  yellow: 'warning',
  orange: 'warning',
  danger: 'danger',
  negative: 'danger',
  error: 'danger',
  red: 'danger',
  rose: 'danger',
  info: 'info',
  blue: 'info',
  sky: 'info',
  cyan: 'info',
  indigo: 'info',
  neutral: 'neutral',
  default: 'neutral',
  gray: 'neutral',
  grey: 'neutral',
  zinc: 'neutral',
  slate: 'neutral',
  muted: 'neutral',
  secondary: 'neutral',
}

/** Every name the vocabulary knows, for docs and tests. */
export const TONE_NAMES: readonly string[] = Object.keys(TONE_ALIASES)

/** A tone or colour name as one of the six tones; null for anything else. */
export function resolveTone(name: string | null | undefined): PanelTone | null {
  if (typeof name !== 'string') return null
  return TONE_ALIASES[name.trim().toLowerCase()] ?? null
}

const TONE_TOKENS: Readonly<Record<PanelTone, string>> = {
  primary: 'var(--uid-accent)',
  success: 'var(--uid-color-success)',
  warning: 'var(--uid-color-warning)',
  danger: 'var(--uid-color-danger)',
  info: 'var(--uid-color-info)',
  neutral: 'var(--uid-color-zinc-400)',
}

/** The kit's CSS token of a tone — follows the theme, dark mode included. */
export function toneToken(tone: PanelTone): string {
  return TONE_TOKENS[tone]
}

/** UidBadge's variants: no primary, and the neutral one is called `default`. */
export type BadgeVariant = 'info' | 'success' | 'warning' | 'danger' | 'default'

/** A name as a UidBadge variant; `default` for an unknown name. */
export function badgeVariant(name: string | null | undefined): BadgeVariant {
  const tone = resolveTone(name)
  if (tone === null || tone === 'neutral') return 'default'
  // The badge has no primary variant; info is the nearest calm colour.
  if (tone === 'primary') return 'info'
  return tone
}

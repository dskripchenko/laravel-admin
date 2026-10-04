/**
 * Colours a widget receives from the backend — a gauge zone, a chart
 * dataset — as names from the panel's tone vocabulary (see ../tones.ts):
 * success, warning, danger, info, primary, neutral, or the plain colour words
 * green, amber, red, gray… Each maps onto the kit's tokens, so a zone follows
 * the theme, dark mode included. Anything else — #hex, rgb(), hsl(), var(--…)
 * — is a CSS colour already and passes through as it is.
 */
import { resolveTone, toneToken, TONE_NAMES } from '../tones'

export { TONE_NAMES }

/** A tone or colour name as a kit token; a CSS colour as it is; '' for nothing. */
export function toneColor(color: string | null | undefined): string {
  if (typeof color !== 'string') return ''
  const name = color.trim()
  if (name === '') return ''
  const tone = resolveTone(name)
  return tone === null ? name : toneToken(tone)
}

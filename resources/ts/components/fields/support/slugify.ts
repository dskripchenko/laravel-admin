/**
 * slugify — the SPA side of the backend's Slug::generate(). Both read the same
 * transliteration table (transliteration.json) and follow the same steps, and
 * both are tested against resources/ts/__fixtures__/slug-cases.json, so the
 * slug the form suggests is the one the server would have produced.
 *
 * The steps, one character (code point) at a time:
 *   1. each character is lower-cased on its own, then the result is walked:
 *   2. a character of the table becomes its transliteration (Cyrillic,
 *      Greek, Latin letters with diacritics);
 *   3. a-z and 0-9 are kept;
 *   4. whitespace, dashes, `_` and the separator itself split words;
 *   5. `@` becomes the word `at`;
 *   6. anything else — punctuation, combining marks, scripts the table does
 *      not cover — is dropped.
 * The words are then joined with the separator.
 */
import table from './transliteration.json'

const MAP: Readonly<Record<string, string>> = (table as { map: Record<string, string> }).map

/** The characters that split words: whitespace, dashes and `_`. */
const BREAKS = new Set<string>([
  ' ', '\t', '\n', '\v', '\f', '\r',
  ' ', ' ', ' ', ' ', ' ', ' ', '　',
  '-', '_', '−',
])

function isBreak(ch: string): boolean {
  if (BREAKS.has(ch)) return true
  const code = ch.codePointAt(0) ?? 0
  // U+2000..U+200A — the typographic spaces; U+2010..U+2015 — the dashes.
  return (code >= 0x2000 && code <= 0x200a) || (code >= 0x2010 && code <= 0x2015)
}

export function slugify(source: unknown, separator = '-'): string {
  const text = source === null || source === undefined ? '' : String(source)
  // Lower-casing one character may give two ('İ' is 'i' and a combining
  // dot), so the lower-cased text is walked again.
  let lower = ''
  for (const raw of text) lower += raw.toLowerCase()

  let out = ''
  for (const ch of lower) {
    const mapped = MAP[ch]
    if (mapped !== undefined) {
      out += mapped
    } else if (/^[a-z0-9]$/.test(ch)) {
      out += ch
    } else if (ch === '@') {
      out += ' at '
    } else if (isBreak(ch) || (separator !== '' && ch === separator)) {
      out += ' '
    }
  }

  return out.split(' ').filter((word) => word !== '').join(separator)
}

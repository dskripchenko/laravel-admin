/**
 * Colour conversions between the formats ColorPicker can store — hex, rgb()
 * and hsl() — and the hex that UidColorPicker works with.
 */

export type ColorFormat = 'hex' | 'rgb' | 'hsl'

export interface Rgba {
  r: number
  g: number
  b: number
  /** 0..1 */
  a: number
}

const clamp = (n: number, min: number, max: number): number => Math.min(max, Math.max(min, n))

function parseHex(input: string): Rgba | null {
  let h = input.replace(/^#/, '')
  if (!/^[0-9a-f]+$/i.test(h)) return null
  if (h.length === 3 || h.length === 4) {
    h = h.split('').map((c) => c + c).join('')
  }
  if (h.length !== 6 && h.length !== 8) return null
  const at = (i: number): number => parseInt(h.slice(i, i + 2), 16)
  return { r: at(0), g: at(2), b: at(4), a: h.length === 8 ? at(6) / 255 : 1 }
}

function parseAlpha(raw: string | undefined): number {
  if (raw === undefined) return 1
  const v = raw.trim()
  return clamp(v.endsWith('%') ? parseFloat(v) / 100 : parseFloat(v), 0, 1)
}

function hslToRgb(h: number, s: number, l: number): [number, number, number] {
  const sat = s / 100
  const lig = l / 100
  const k = (n: number): number => (n + h / 30) % 12
  const a = sat * Math.min(lig, 1 - lig)
  const f = (n: number): number => lig - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))
  return [Math.round(f(0) * 255), Math.round(f(8) * 255), Math.round(f(4) * 255)]
}

function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
  const rr = r / 255
  const gg = g / 255
  const bb = b / 255
  const max = Math.max(rr, gg, bb)
  const min = Math.min(rr, gg, bb)
  const l = (max + min) / 2
  if (max === min) return [0, 0, Math.round(l * 100)]
  const d = max - min
  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
  let h: number
  if (max === rr) h = (gg - bb) / d + (gg < bb ? 6 : 0)
  else if (max === gg) h = (bb - rr) / d + 2
  else h = (rr - gg) / d + 4
  return [Math.round(h * 60), Math.round(s * 100), Math.round(l * 100)]
}

/** Parses hex (3/4/6/8 digits), rgb()/rgba() and hsl()/hsla(); null when unrecognised. */
export function parseColor(input: unknown): Rgba | null {
  if (typeof input !== 'string') return null
  const s = input.trim().toLowerCase()
  if (s === '') return null
  if (s.startsWith('#')) return parseHex(s)

  const fn = /^(rgba?|hsla?)\(([^)]*)\)$/.exec(s)
  if (!fn) return parseHex(s)
  const parts = (fn[2] ?? '').split(/[\s,/]+/).filter((p) => p !== '')
  if (parts.length < 3) return null
  const [p0 = '0', p1 = '0', p2 = '0', p3] = parts
  const a = parseAlpha(p3)

  if (fn[1]?.startsWith('rgb')) {
    const ch = (v: string): number =>
      clamp(Math.round(v.endsWith('%') ? (parseFloat(v) / 100) * 255 : parseFloat(v)), 0, 255)
    const [r, g, b] = [ch(p0), ch(p1), ch(p2)]
    if ([r, g, b].some(Number.isNaN)) return null
    return { r, g, b, a }
  }
  const [r, g, b] = hslToRgb(parseFloat(p0), parseFloat(p1), parseFloat(p2))
  if ([r, g, b].some(Number.isNaN)) return null
  return { r, g, b, a }
}

const hex2 = (n: number): string => clamp(Math.round(n), 0, 255).toString(16).padStart(2, '0')

/** Formats a colour; the alpha channel is written only when it is below 1. */
export function formatColor(c: Rgba, format: ColorFormat = 'hex'): string {
  const hasAlpha = c.a < 1
  const alpha = Math.round(c.a * 100) / 100
  if (format === 'rgb') {
    return hasAlpha ? `rgba(${c.r}, ${c.g}, ${c.b}, ${alpha})` : `rgb(${c.r}, ${c.g}, ${c.b})`
  }
  if (format === 'hsl') {
    const [h, s, l] = rgbToHsl(c.r, c.g, c.b)
    return hasAlpha ? `hsla(${h}, ${s}%, ${l}%, ${alpha})` : `hsl(${h}, ${s}%, ${l}%)`
  }
  return `#${hex2(c.r)}${hex2(c.g)}${hex2(c.b)}${hasAlpha ? hex2(c.a * 255) : ''}`
}

/** Any supported colour string as the hex UidColorPicker understands. */
export function toHex(input: unknown): string | null {
  const c = parseColor(input)
  return c === null ? null : formatColor(c, 'hex')
}

/**
 * The source of a ```video or ```audio block in markdown: a bare URL (or one
 * per line — a playlist, or video sources), JSON, or a small YAML subset:
 *
 *     src: /media/intro.mp4
 *     poster: /media/intro.jpg
 *     tracks:
 *       - src: /media/intro.en.vtt
 *         srclang: en
 *         label: English
 *         default: true
 *     peaks: [0.1, 0.4, 0.8]
 *
 * The YAML is what such a block needs and no more: `key: value` pairs, a list
 * of scalars or of flat maps under a key, a top-level list, quoted strings,
 * numbers, booleans, null and `[a, b]` flow lists. Anything else is an error
 * with its line.
 */

export class MediaBlockError extends Error {}

type Scalar = string | number | boolean | null
type Value = Scalar | Scalar[] | Record<string, unknown> | Record<string, unknown>[] | unknown[]

const KEY_RE = /^([A-Za-z_][\w-]*)\s*:(?:\s+(.*))?$/

function scalar(raw: string): Value {
  const v = raw.trim()
  if (v === '') return ''
  if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
    if (v.startsWith('"')) {
      try {
        return JSON.parse(v) as string
      } catch {
        return v.slice(1, -1)
      }
    }
    return v.slice(1, -1).replace(/''/g, "'")
  }
  if (v.startsWith('[') && v.endsWith(']')) {
    const inner = v.slice(1, -1).trim()
    return inner === '' ? [] : inner.split(',').map((p) => scalar(p) as Scalar)
  }
  if (v === 'true') return true
  if (v === 'false') return false
  if (v === 'null' || v === '~') return null
  if (/^-?\d+(\.\d+)?$/.test(v)) return Number(v)
  return v
}

function indentOf(line: string): number {
  return line.length - line.trimStart().length
}

/** A list starting at `lines[i]`, its items at `indent`; returns the list and the next line. */
function parseList(lines: string[], i: number, indent: number): [unknown[], number] {
  const out: unknown[] = []
  while (i < lines.length) {
    const line = lines[i]
    if (indentOf(line) < indent || !line.trimStart().startsWith('-')) break
    if (indentOf(line) > indent) throw new MediaBlockError(`line ${i + 1}: unexpected indentation`)
    const first = line.trimStart().slice(1).trim()
    const m = KEY_RE.exec(first)
    if (!m) {
      out.push(scalar(first))
      i++
      continue
    }
    // A map: the first key on the dash's line, the rest indented under it.
    const item: Record<string, unknown> = { [m[1]]: scalar(m[2] ?? '') }
    const inner = indentOf(line) + (line.trimStart().length - line.trimStart().slice(1).trimStart().length)
    i++
    while (i < lines.length && indentOf(lines[i]) >= inner && !lines[i].trimStart().startsWith('-')) {
      const km = KEY_RE.exec(lines[i].trim())
      if (!km) throw new MediaBlockError(`line ${i + 1}: expected "key: value"`)
      item[km[1]] = scalar(km[2] ?? '')
      i++
    }
    out.push(item)
  }
  return [out, i]
}

function parseYamlSubset(text: string): Value {
  const lines = text.split(/\r?\n/).map((l) => l.replace(/\s+#.*$/, '')).filter((l) => l.trim() !== '' && !l.trim().startsWith('#'))
  if (lines.length > 0 && lines[0].trimStart().startsWith('-')) {
    const [list, next] = parseList(lines, 0, indentOf(lines[0]))
    if (next < lines.length) throw new MediaBlockError(`line ${next + 1}: unexpected content after the list`)
    return list as Value
  }
  const out: Record<string, unknown> = {}
  let i = 0
  while (i < lines.length) {
    const line = lines[i]
    if (indentOf(line) !== 0) throw new MediaBlockError(`line ${i + 1}: unexpected indentation`)
    const m = KEY_RE.exec(line.trim())
    if (!m) throw new MediaBlockError(`line ${i + 1}: expected "key: value"`)
    i++
    if (m[2] !== undefined && m[2].trim() !== '') {
      out[m[1]] = scalar(m[2])
      continue
    }
    if (i < lines.length && indentOf(lines[i]) > 0 && lines[i].trimStart().startsWith('-')) {
      const [list, next] = parseList(lines, i, indentOf(lines[i]))
      out[m[1]] = list
      i = next
    } else {
      out[m[1]] = null
    }
  }
  return out
}

/**
 * The block's source as data: an object, a list, or `{src}` for a bare URL.
 * Throws MediaBlockError with the reason.
 */
export function parseMediaBlock(code: string): Record<string, unknown> | unknown[] {
  const source = code.trim()
  if (source === '') throw new MediaBlockError('the block is empty')
  if (source.startsWith('{') || source.startsWith('[')) {
    let data: unknown
    try {
      data = JSON.parse(source)
    } catch (error) {
      throw new MediaBlockError(error instanceof Error ? error.message : String(error))
    }
    if (!data || typeof data !== 'object') throw new MediaBlockError('expected an object or a list')
    return data as Record<string, unknown> | unknown[]
  }
  const lines = source.split(/\r?\n/).map((l) => l.trim()).filter((l) => l !== '' && !l.startsWith('#'))
  // Bare addresses, one per line.
  if (lines.every((l) => !KEY_RE.test(l) && !l.startsWith('-') && !/\s/.test(l))) {
    return lines.length === 1 ? { src: lines[0] } : lines.map((src) => ({ src }))
  }
  const data = parseYamlSubset(source)
  if (!data || typeof data !== 'object') throw new MediaBlockError('expected "key: value" pairs or a list')
  return data as Record<string, unknown> | unknown[]
}

/**
 * Before and after of one field, as the audit shows them.
 *
 * A short value reads as `было → стало`. A long text is compared line by line
 * (a single long line — word by word), and only the changed lines are shown
 * with a little context, the unchanged runs folded. A JSON value — an object,
 * an array or a string holding one — is laid out as `path: value` lines first,
 * so the change of one key is one line, not the whole document.
 */

export type DiffOp = { kind: 'same' | 'del' | 'add'; text: string }
export type DiffRow = DiffOp | { kind: 'fold'; count: number }

export interface ValueDiff {
  /** inline — a short value; lines — line by line; words — one long line word by word. */
  mode: 'inline' | 'lines' | 'words'
  rows: DiffRow[]
  /** Lines (or words) added and removed. */
  added: number
  removed: number
}

/** Longer than this, or with a line break, a text is compared in parts. */
export const LONG_TEXT = 80

/** A string that is a JSON object or array, parsed; else undefined. */
function parseJson(v: unknown): unknown {
  if (typeof v !== 'string') return undefined
  const t = v.trim()
  if (!(t.startsWith('{') && t.endsWith('}')) && !(t.startsWith('[') && t.endsWith(']'))) return undefined
  try {
    return JSON.parse(t)
  } catch {
    return undefined
  }
}

function isStructured(v: unknown): boolean {
  return (v !== null && typeof v === 'object') || parseJson(v) !== undefined
}

/** `path: value` lines of a JSON value, the keys sorted so the order does not count as a change. */
export function jsonLines(value: unknown, prefix = ''): string[] {
  const v = parseJson(value) ?? value
  if (v === null || typeof v !== 'object') {
    return [`${prefix === '' ? '' : `${prefix}: `}${JSON.stringify(v)}`]
  }
  const entries: Array<[string, unknown]> = Array.isArray(v)
    ? v.map((x, i) => [`${prefix}[${i}]`, x])
    : Object.keys(v as Record<string, unknown>).sort().map((k) => [prefix === '' ? k : `${prefix}.${k}`, (v as Record<string, unknown>)[k]])
  if (entries.length === 0) return [`${prefix === '' ? '' : `${prefix}: `}${Array.isArray(v) ? '[]' : '{}'}`]
  return entries.flatMap(([path, x]) => jsonLines(x, path))
}

/** The longest common subsequence of two token lists, as operations. */
export function diffTokens(a: ReadonlyArray<string>, b: ReadonlyArray<string>): DiffOp[] {
  // Trim the common head and tail: most edits touch a small middle.
  let start = 0
  while (start < a.length && start < b.length && a[start] === b[start]) start++
  let endA = a.length
  let endB = b.length
  while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) { endA--; endB-- }
  const midA = a.slice(start, endA)
  const midB = b.slice(start, endB)
  const n = midA.length
  const m = midB.length
  const ops: DiffOp[] = a.slice(0, start).map((text) => ({ kind: 'same', text }))
  if (n * m > 4_000_000) {
    // Too large to align: all of it removed and added.
    ops.push(...midA.map((text) => ({ kind: 'del' as const, text })), ...midB.map((text) => ({ kind: 'add' as const, text })))
  } else {
    const lcs: number[][] = Array.from({ length: n + 1 }, () => new Array<number>(m + 1).fill(0))
    for (let i = n - 1; i >= 0; i--) {
      for (let j = m - 1; j >= 0; j--) {
        lcs[i][j] = midA[i] === midB[j] ? lcs[i + 1][j + 1] + 1 : Math.max(lcs[i + 1][j], lcs[i][j + 1])
      }
    }
    let i = 0
    let j = 0
    while (i < n && j < m) {
      if (midA[i] === midB[j]) { ops.push({ kind: 'same', text: midA[i] }); i++; j++ }
      else if (lcs[i + 1][j] >= lcs[i][j + 1]) { ops.push({ kind: 'del', text: midA[i] }); i++ }
      else { ops.push({ kind: 'add', text: midB[j] }); j++ }
    }
    while (i < n) ops.push({ kind: 'del', text: midA[i++] })
    while (j < m) ops.push({ kind: 'add', text: midB[j++] })
  }
  ops.push(...a.slice(endA).map((text) => ({ kind: 'same' as const, text })))
  return ops
}

/** Unchanged runs longer than twice `context` folded in the middle. */
export function foldSame(ops: ReadonlyArray<DiffOp>, context: number): DiffRow[] {
  const out: DiffRow[] = []
  let i = 0
  while (i < ops.length) {
    if (ops[i].kind !== 'same') { out.push(ops[i]); i++; continue }
    let j = i
    while (j < ops.length && ops[j].kind === 'same') j++
    const run = ops.slice(i, j)
    const head = i === 0 ? 0 : context
    const tail = j === ops.length ? 0 : context
    if (run.length > head + tail + 1) {
      out.push(...run.slice(0, head), { kind: 'fold', count: run.length - head - tail }, ...run.slice(run.length - tail))
    } else {
      out.push(...run)
    }
    i = j
  }
  return out
}

function text(v: unknown): string {
  if (v === null || v === undefined) return ''
  return typeof v === 'string' ? v : String(v)
}

function counted(mode: ValueDiff['mode'], ops: DiffOp[], rows: DiffRow[]): ValueDiff {
  return {
    mode,
    rows,
    added: ops.filter((o) => o.kind === 'add').length,
    removed: ops.filter((o) => o.kind === 'del').length,
  }
}

/**
 * How to show the change of one value: inline for a short one, else the
 * changed lines (or words) with `context` unchanged ones around them.
 */
export function valueDiff(before: unknown, after: unknown, context = 1): ValueDiff {
  if (isStructured(before) || isStructured(after)) {
    const ops = diffTokens(before == null ? [] : jsonLines(before), after == null ? [] : jsonLines(after))
    return counted('lines', ops, foldSame(ops, context))
  }
  const a = text(before)
  const b = text(after)
  const multiline = a.includes('\n') || b.includes('\n')
  if (!multiline && a.length <= LONG_TEXT && b.length <= LONG_TEXT) {
    return { mode: 'inline', rows: [], added: 0, removed: 0 }
  }
  if (multiline) {
    const ops = diffTokens(a === '' ? [] : a.split('\n'), b === '' ? [] : b.split('\n'))
    return counted('lines', ops, foldSame(ops, context))
  }
  // One long line: by words, the spaces kept with the word before them.
  const words = (s: string): string[] => s.match(/\S+\s*/g) ?? []
  const ops = diffTokens(words(a), words(b))
  return counted('words', ops, foldSame(ops, 4))
}

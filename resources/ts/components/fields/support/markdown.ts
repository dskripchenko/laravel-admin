/**
 * A small, dependency-free markdown → HTML renderer.
 *
 * It covers the everyday subset — headings, paragraphs, emphasis, inline code,
 * fenced code, links, images, block quotes and callouts, lists, tables and
 * rules — which is what a description, a note or a documentation page in an
 * admin usually holds. A host that needs the full CommonMark grammar registers
 * its own `markdown` field, entry or layout.
 *
 * Safety: the source is HTML-escaped BEFORE any markup is produced, so raw
 * HTML in the markdown is shown as text, never executed; link and image URLs
 * are limited to http(s), mailto, relative and anchor targets.
 */
import { trSafe } from '../../../stores/i18n'

const ESCAPES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
}

export function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (ch) => ESCAPES[ch] ?? ch)
}

function unescapeHtml(text: string): string {
  return text
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
}

/** A heading met while rendering, for a table of contents. */
export interface MarkdownHeading {
  level: number
  /** The heading's plain text. */
  text: string
  id: string
}

/** Turns heading text into a unique anchor id; shared across the chunks of one document. */
export type Slugger = (text: string) => string

export function createSlugger(): Slugger {
  const seen = new Map<string, number>()
  return (text: string): string => {
    const base =
      text
        .toLowerCase()
        .trim()
        .replace(/[^\p{L}\p{N}\s_-]/gu, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '') || 'section'
    const count = seen.get(base) ?? 0
    seen.set(base, count + 1)
    return count === 0 ? base : `${base}-${count}`
  }
}

export interface MarkdownOptions {
  /** Gives the headings anchor ids. */
  slugger?: Slugger | null
  /** Called for every heading, in document order. */
  onHeading?: (heading: MarkdownHeading) => void
  /**
   * The base relative links resolve against, the way a browser resolves them
   * against `<base href>`. Absolute, anchor and scheme links are left alone.
   */
  linkBase?: string | null
  /** Drops a trailing `.md` from relative links resolved through linkBase. */
  stripMdExtension?: boolean
  /** The base relative image paths resolve against. */
  imageBase?: string | null
  /**
   * Opens relative and anchor links in place. By default every link opens in
   * a new tab, which suits a note in a form; a documentation page wants its
   * own links to navigate.
   */
  internalLinksInPlace?: boolean
}

/** A URL is allowed when it has no scheme, or one of the harmless ones. */
function safeUrl(url: string): string | null {
  const trimmed = url.trim()
  // Entities were escaped already; decode the colon tricks before testing.
  const probe = trimmed.replace(/&#0*58;|&colon;/gi, ':').replace(/\s+/g, '').toLowerCase()
  const scheme = /^([a-z][a-z0-9+.-]*):/.exec(probe)
  if (scheme && !['http', 'https', 'mailto'].includes(scheme[1] ?? '')) return null
  return trimmed
}

function isRelative(url: string): boolean {
  return !/^([a-z][a-z0-9+.-]*:|\/|#|\?)/i.test(url)
}

/**
 * Resolves an escaped relative URL against a base. The result is escaped
 * again; an absolute base keeps its origin, a path base yields a path.
 */
export function resolveAgainst(escapedUrl: string, base: string, stripMd = false): string {
  let url = unescapeHtml(escapedUrl)
  if (stripMd) {
    url = url.replace(/\.md(?=$|[?#])/i, '')
  }
  try {
    const absoluteBase = /^[a-z][a-z0-9+.-]*:\/\//i.test(base)
    const resolved = new URL(url, absoluteBase ? base : `http://base.invalid${base.startsWith('/') ? '' : '/'}${base}`)
    const out = absoluteBase ? resolved.href : resolved.pathname + resolved.search + resolved.hash
    return escapeHtml(out)
  } catch {
    return escapedUrl
  }
}

/** Inline markup over an already escaped line. */
function renderInline(escaped: string, opts: MarkdownOptions = {}): string {
  const slots: string[] = []
  const stash = (html: string): string => `${slots.push(html) - 1}`

  let out = escaped.replace(/`([^`]+)`/g, (_m, code: string) => stash(`<code>${code}</code>`))

  out = out.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+&quot;[^)]*&quot;)?\)/g, (m, alt: string, url: string) => {
    let src = safeUrl(url)
    if (src === null) return m
    if (opts.imageBase && isRelative(src)) src = resolveAgainst(src, opts.imageBase)
    return stash(`<img src="${src}" alt="${alt}" loading="lazy">`)
  })
  out = out.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;[^)]*&quot;)?\)/g, (m, text: string, url: string) => {
    let href = safeUrl(url)
    if (href === null) return m
    const relative = isRelative(href)
    if (opts.linkBase && relative) {
      href = resolveAgainst(href, opts.linkBase, opts.stripMdExtension ?? false)
    }
    const external = /^(https?:)?\/\//i.test(href) || /^mailto:/i.test(href)
    const inPlace = opts.internalLinksInPlace === true && !external
    return stash(
      inPlace
        ? `<a href="${href}">${text}</a>`
        : `<a href="${href}" target="_blank" rel="noopener noreferrer">${text}</a>`,
    )
  })

  out = out
    .replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/g, '<strong>$1</strong>')
    .replace(/(?<!\w)__(?=\S)(.+?)(?<=\S)__(?!\w)/g, '<strong>$1</strong>')
    .replace(/\*(?=\S)(.+?)(?<=\S)\*/g, '<em>$1</em>')
    // An underscore inside a word (snake_case) is not emphasis.
    .replace(/(?<!\w)_(?=\S)(.+?)(?<=\S)_(?!\w)/g, '<em>$1</em>')
    .replace(/~~(?=\S)(.+?)(?<=\S)~~/g, '<del>$1</del>')

  return out.replace(/(\d+)/g, (_m, i: string) => slots[Number(i)] ?? '')
}

/** The plain text of rendered inline HTML: for anchor ids and the table of contents. */
function plainText(html: string): string {
  return unescapeHtml(html.replace(/<[^>]+>/g, ''))
}

const LIST_ITEM = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/
const TABLE_SEPARATOR = /^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/

function splitRow(line: string): string[] {
  let row = line.trim()
  if (row.startsWith('|')) row = row.slice(1)
  if (row.endsWith('|') && !row.endsWith('\\|')) row = row.slice(0, -1)
  return row.split(/(?<!\\)\|/).map((cell) => cell.trim().replace(/\\\|/g, '|'))
}

/** The callout kinds, keyed by the marker that opens them: `[!NOTE]` or `**Note**`. */
const CALLOUTS: Record<string, string> = {
  note: 'note',
  info: 'note',
  tip: 'tip',
  hint: 'tip',
  important: 'important',
  warning: 'warning',
  caution: 'caution',
  danger: 'caution',
  'примечание': 'note',
  'заметка': 'note',
  'совет': 'tip',
  'важно': 'important',
  'внимание': 'warning',
  'предупреждение': 'warning',
  'осторожно': 'caution',
}

function calloutTitle(kind: string): string {
  switch (kind) {
    case 'tip':
      return trSafe('Совет')
    case 'important':
      return trSafe('Важно')
    case 'warning':
      return trSafe('Внимание')
    case 'caution':
      return trSafe('Осторожно')
    default:
      return trSafe('Примечание')
  }
}

export function renderMarkdown(source: string | null | undefined, opts: MarkdownOptions = {}): string {
  if (!source) return ''
  const lines = escapeHtml(source.replace(/\r\n?/g, '\n')).split('\n')
  const html: string[] = []
  let paragraph: string[] = []
  const inline = (text: string): string => renderInline(text, opts)

  const flushParagraph = (): void => {
    if (paragraph.length > 0) {
      html.push(`<p>${inline(paragraph.join('\n'))}</p>`)
      paragraph = []
    }
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] ?? ''

    const fence = /^\s*(```|~~~)\s*([\w+#.-]*)[^`]*$/.exec(line)
    if (fence) {
      flushParagraph()
      const body: string[] = []
      i++
      while (i < lines.length && !(lines[i] ?? '').trim().startsWith(fence[1] ?? '```')) {
        body.push(lines[i] ?? '')
        i++
      }
      const cls = fence[2] ? ` class="language-${fence[2]}"` : ''
      html.push(`<pre><code${cls}>${body.join('\n')}</code></pre>`)
      continue
    }

    if (line.trim() === '') {
      flushParagraph()
      continue
    }

    const heading = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line)
    if (heading) {
      flushParagraph()
      const level = heading[1]?.length ?? 1
      const content = inline(heading[2] ?? '')
      if (opts.slugger) {
        const text = plainText(content)
        const id = opts.slugger(text)
        opts.onHeading?.({ level, text, id })
        html.push(`<h${level} id="${escapeHtml(id)}">${content}</h${level}>`)
      } else {
        html.push(`<h${level}>${content}</h${level}>`)
      }
      continue
    }

    if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
      flushParagraph()
      html.push('<hr>')
      continue
    }

    // A table: a row of cells, then the separator row of dashes.
    if (line.includes('|') && TABLE_SEPARATOR.test(lines[i + 1] ?? '') && (lines[i + 1] ?? '').includes('|')) {
      flushParagraph()
      const header = splitRow(line)
      const aligns = splitRow(lines[i + 1] ?? '').map((cell) => {
        const left = cell.startsWith(':')
        const right = cell.endsWith(':')
        return left && right ? 'center' : right ? 'right' : left ? 'left' : ''
      })
      const cell = (tag: 'th' | 'td', text: string, idx: number): string => {
        const align = aligns[idx] ? ` style="text-align:${aligns[idx]}"` : ''
        return `<${tag}${align}>${inline(text)}</${tag}>`
      }
      const rows: string[] = []
      i += 2
      while (i < lines.length && (lines[i] ?? '').includes('|') && (lines[i] ?? '').trim() !== '') {
        const cells = splitRow(lines[i] ?? '')
        rows.push(`<tr>${header.map((_h, idx) => cell('td', cells[idx] ?? '', idx)).join('')}</tr>`)
        i++
      }
      i--
      html.push(
        '<div class="admin-markdown__table"><table>'
          + `<thead><tr>${header.map((h, idx) => cell('th', h, idx)).join('')}</tr></thead>`
          + `<tbody>${rows.join('')}</tbody></table></div>`,
      )
      continue
    }

    if (/^\s*&gt;/.test(line)) {
      flushParagraph()
      const quoted: string[] = []
      while (i < lines.length && /^\s*&gt;/.test(lines[i] ?? '')) {
        quoted.push((lines[i] ?? '').replace(/^\s*&gt;\s?/, ''))
        i++
      }
      i--
      html.push(renderQuote(quoted, opts))
      continue
    }

    if (LIST_ITEM.test(line)) {
      flushParagraph()
      const list = renderList(lines, i, opts)
      html.push(list.html)
      i = list.next - 1
      continue
    }

    paragraph.push(line)
  }
  flushParagraph()

  return html.join('\n')
}

/** The width of a line's leading whitespace, a tab counting as four. */
function indentOf(line: string): number {
  const lead = /^[ \t]*/.exec(line)?.[0] ?? ''
  return lead.replace(/\t/g, '    ').length
}

/** Whether a line starts a block of its own rather than continuing a list item's text. */
function opensBlock(line: string): boolean {
  return /^\s*(#{1,6}\s|&gt;|```|~~~|\|)/.test(line) || /^\s*([-*_])(\s*\1){2,}\s*$/.test(line)
}

/**
 * A list starting at lines[start], nested lists included: a line indented
 * past an item's marker belongs to that item — a sub-list, a continuation
 * paragraph, a code block — and is rendered as the item's own markdown. A
 * blank line inside the list does not end it while the list goes on below.
 * The lines are escaped already.
 */
function renderList(lines: string[], start: number, opts: MarkdownOptions): { html: string; next: number } {
  const first = LIST_ITEM.exec(lines[start] ?? '')
  const indent = indentOf(first?.[1] ?? '')
  const ordered = /\d/.test(first?.[2] ?? '')
  const items: string[] = []
  let i = start

  while (i < lines.length) {
    const m = LIST_ITEM.exec(lines[i] ?? '')
    if (!m || indentOf(m[1] ?? '') > indent + 1 || indentOf(m[1] ?? '') < indent || /\d/.test(m[2] ?? '') !== ordered) break

    const text: string[] = [m[3] ?? '']
    const body: string[] = []
    i++
    while (i < lines.length) {
      const next = lines[i] ?? ''
      if (next.trim() === '') {
        // A blank line: the item goes on only if something indented follows.
        let j = i + 1
        while (j < lines.length && (lines[j] ?? '').trim() === '') j++
        if (j < lines.length && indentOf(lines[j] ?? '') > indent + 1) {
          for (; i < j; i++) body.push('')
          continue
        }
        break
      }
      if (indentOf(next) > indent + 1) {
        body.push(next)
        i++
        continue
      }
      // A lazy continuation: plain text right under the item, before any nested block.
      if (body.length === 0 && !LIST_ITEM.test(next) && !opensBlock(next)) {
        text.push(next.trim())
        i++
        continue
      }
      break
    }

    let inner = renderInline(text.join('\n'), opts)
    if (body.some((l) => l.trim() !== '')) {
      const cut = Math.min(...body.filter((l) => l.trim() !== '').map(indentOf))
      const dedented = body.map((l) => l.replace(/\t/g, '    ').slice(cut))
      inner += renderEscapedBlocks(dedented, opts)
    }
    items.push(`<li>${inner}</li>`)

    // Blank lines between the items of one list keep it going.
    let j = i
    while (j < lines.length && (lines[j] ?? '').trim() === '') j++
    const after = LIST_ITEM.exec(lines[j] ?? '')
    if (j > i && after && indentOf(after[1] ?? '') <= indent + 1 && indentOf(after[1] ?? '') >= indent && /\d/.test(after[2] ?? '') === ordered) {
      i = j
    }
  }

  const tag = ordered ? 'ol' : 'ul'
  const startAt = ordered ? Number.parseInt(first?.[2] ?? '1', 10) : 1
  const startAttr = ordered && startAt !== 1 && Number.isFinite(startAt) ? ` start="${startAt}"` : ''
  return { html: `<${tag}${startAttr}>${items.join('')}</${tag}>`, next: i }
}

/**
 * A block quote, or a callout when its first line is a marker: GitHub's
 * `[!NOTE]` (the marker line is replaced with a title) or a bold `**Note**`
 * (kept as written).
 */
function renderQuote(quoted: string[], opts: MarkdownOptions): string {
  const first = (quoted[0] ?? '').trim()
  const github = /^\[!(\w+)\]\s*$/.exec(first)
  if (github) {
    const kind = CALLOUTS[(github[1] ?? '').toLowerCase()]
    if (kind) {
      const title = `<p class="admin-markdown__callout-title">${escapeHtml(calloutTitle(kind))}</p>`
      return `<blockquote class="admin-markdown__callout admin-markdown__callout--${kind}">${title}${renderEscapedBlocks(quoted.slice(1), opts)}</blockquote>`
    }
  }
  const bold = /^\*\*([^*]+?):?\*\*:?/.exec(first)
  if (bold) {
    const kind = CALLOUTS[(bold[1] ?? '').trim().toLowerCase()]
    if (kind) {
      return `<blockquote class="admin-markdown__callout admin-markdown__callout--${kind}">${renderEscapedBlocks(quoted, opts)}</blockquote>`
    }
  }
  return `<blockquote>${renderEscapedBlocks(quoted, opts)}</blockquote>`
}

/** Renders lines that were escaped already (the body of a block quote). */
function renderEscapedBlocks(lines: string[], opts: MarkdownOptions): string {
  // Unescape and run the full renderer: it escapes again exactly once.
  return renderMarkdown(unescapeHtml(lines.join('\n')), opts)
}

/** A piece of a markdown document: text to render, or a top-level fenced code block. */
export type MarkdownSegment =
  | { kind: 'text'; source: string }
  | { kind: 'code'; code: string; language: string }

/**
 * Splits a document at its top-level fenced code blocks, so that a caller can
 * draw the code with a real highlighter and the rest with renderMarkdown.
 * Fences inside block quotes stay in the text.
 */
export function splitMarkdown(source: string | null | undefined): MarkdownSegment[] {
  if (!source) return []
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const segments: MarkdownSegment[] = []
  let text: string[] = []

  const flushText = (): void => {
    if (text.some((l) => l.trim() !== '')) segments.push({ kind: 'text', source: text.join('\n') })
    text = []
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] ?? ''
    const fence = /^ {0,3}(`{3,}|~{3,})\s*([\w+#.-]*)[^`]*$/.exec(line)
    if (!fence) {
      text.push(line)
      continue
    }
    flushText()
    const marker = fence[1] ?? '```'
    const body: string[] = []
    i++
    while (i < lines.length && !(lines[i] ?? '').trim().startsWith(marker)) {
      body.push(lines[i] ?? '')
      i++
    }
    segments.push({ kind: 'code', code: body.join('\n'), language: (fence[2] ?? '').toLowerCase() })
  }
  flushText()

  return segments
}

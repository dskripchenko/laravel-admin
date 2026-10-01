/**
 * A small, dependency-free markdown → HTML renderer for previews.
 *
 * It covers the everyday subset — headings, paragraphs, emphasis, inline code,
 * fenced code, links, images, block quotes, lists and rules — which is what a
 * description or a note in an admin form usually holds. A host that needs the
 * full CommonMark grammar registers its own `markdown` field or entry.
 *
 * Safety: the source is HTML-escaped BEFORE any markup is produced, so raw
 * HTML in the markdown is shown as text, never executed; link and image URLs
 * are limited to http(s), mailto, relative and anchor targets.
 */

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

/** A URL is allowed when it has no scheme, or one of the harmless ones. */
function safeUrl(url: string): string | null {
  const trimmed = url.trim()
  // Entities were escaped already; decode the colon tricks before testing.
  const probe = trimmed.replace(/&#0*58;|&colon;/gi, ':').replace(/\s+/g, '').toLowerCase()
  const scheme = /^([a-z][a-z0-9+.-]*):/.exec(probe)
  if (scheme && !['http', 'https', 'mailto'].includes(scheme[1] ?? '')) return null
  return trimmed
}

/** Inline markup over an already escaped line. */
function renderInline(escaped: string): string {
  const slots: string[] = []
  const stash = (html: string): string => `\uE000${slots.push(html) - 1}\uE000`

  let out = escaped.replace(/`([^`]+)`/g, (_m, code: string) => stash(`<code>${code}</code>`))

  out = out.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (m, alt: string, url: string) => {
    const src = safeUrl(url)
    return src === null ? m : stash(`<img src="${src}" alt="${alt}" loading="lazy">`)
  })
  out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, text: string, url: string) => {
    const href = safeUrl(url)
    return href === null
      ? m
      : stash(`<a href="${href}" target="_blank" rel="noopener noreferrer">${text}</a>`)
  })

  out = out
    .replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/g, '<strong>$1</strong>')
    .replace(/(?<!\w)__(?=\S)(.+?)(?<=\S)__(?!\w)/g, '<strong>$1</strong>')
    .replace(/\*(?=\S)(.+?)(?<=\S)\*/g, '<em>$1</em>')
    // An underscore inside a word (snake_case) is not emphasis.
    .replace(/(?<!\w)_(?=\S)(.+?)(?<=\S)_(?!\w)/g, '<em>$1</em>')
    .replace(/~~(?=\S)(.+?)(?<=\S)~~/g, '<del>$1</del>')

  return out.replace(/\uE000(\d+)\uE000/g, (_m, i: string) => slots[Number(i)] ?? '')
}

const LIST_ITEM = /^\s*([-*+]|\d+[.)])\s+(.*)$/

export function renderMarkdown(source: string | null | undefined): string {
  if (!source) return ''
  const lines = escapeHtml(source.replace(/\r\n?/g, '\n')).split('\n')
  const html: string[] = []
  let paragraph: string[] = []

  const flushParagraph = (): void => {
    if (paragraph.length > 0) {
      html.push(`<p>${renderInline(paragraph.join('\n'))}</p>`)
      paragraph = []
    }
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] ?? ''

    const fence = /^\s*(```|~~~)\s*([\w+-]*)\s*$/.exec(line)
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
      html.push(`<h${level}>${renderInline(heading[2] ?? '')}</h${level}>`)
      continue
    }

    if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
      flushParagraph()
      html.push('<hr>')
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
      // The quote's body is escaped already; render it without escaping twice.
      html.push(`<blockquote>${renderEscapedBlocks(quoted)}</blockquote>`)
      continue
    }

    const item = LIST_ITEM.exec(line)
    if (item) {
      flushParagraph()
      const ordered = /\d/.test(item[1] ?? '')
      const items: string[] = []
      while (i < lines.length) {
        const m = LIST_ITEM.exec(lines[i] ?? '')
        if (!m || /\d/.test(m[1] ?? '') !== ordered) break
        items.push(`<li>${renderInline(m[2] ?? '')}</li>`)
        i++
      }
      i--
      const tag = ordered ? 'ol' : 'ul'
      html.push(`<${tag}>${items.join('')}</${tag}>`)
      continue
    }

    paragraph.push(line)
  }
  flushParagraph()

  return html.join('\n')
}

/** Renders lines that were escaped already (the body of a block quote). */
function renderEscapedBlocks(lines: string[]): string {
  // Unescape and run the full renderer: it escapes again exactly once.
  const raw = lines
    .join('\n')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
  return renderMarkdown(raw)
}

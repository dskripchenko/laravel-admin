/**
 * The preview of a block tree: the page's HTML, POSTed for the current tree,
 * shown in an iframe of the same origin.
 *
 * The request: POST `{nodes, ...params}` as JSON to the layout's preview
 * address, with the panel's session and CSRF token (the admin client's
 * X-XSRF-TOKEN, else the page's csrf-token meta). The answer: the HTML.
 *
 * The page is shown from a blob: URL — a document of the panel's origin, with
 * the page's own scripts, and none of the panel's — under a `<base>` pointing
 * at the site, so the page's relative addresses still resolve.
 *
 * The messages: a block of the page is an element with the marker attribute
 * (`data-builder-ref`) holding the node's ref. A page script may post
 * `{type: 'builder:select', ref}` to the parent; the panel posts
 * `{type: 'builder:highlight', ref}` to the page. The panel also reads the
 * markers itself — a click inside one selects the node, a selected node is
 * outlined and scrolled to — so a page needs only the markers.
 */
import { getAdminClient, hasAdminClient } from '../../stores/registry'
import { trSafe as tr } from '../../stores/i18n'
import type { BlockRef } from './tree'

export const SELECT_MESSAGE = 'builder:select'
export const HIGHLIGHT_MESSAGE = 'builder:highlight'

/** The address made absolute; only the panel's own origin is allowed. */
export function previewTarget(url: string): URL {
  const target = new URL(url, window.location.href)
  if (target.origin !== window.location.origin) {
    throw new Error(tr('Предпросмотр можно загрузить только с этого же сайта'))
  }
  return target
}

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return m ? decodeURIComponent(m[1]) : null
}

/** POSTs the tree and resolves to the page's HTML. */
export async function fetchPreview(url: string, body: Record<string, unknown>, signal?: AbortSignal): Promise<string> {
  const target = previewTarget(url)
  const raw = hasAdminClient() ? getAdminClient().raw : null
  if (raw && typeof raw.post === 'function') {
    const res = await raw.post<string>(target.href, body, {
      signal,
      responseType: 'text',
      headers: { Accept: 'text/html' },
      transformResponse: [(data: unknown) => data],
    })
    return typeof res.data === 'string' ? res.data : String(res.data ?? '')
  }
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'text/html',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const xsrf = readCookie('XSRF-TOKEN')
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
  else if (meta) headers['X-CSRF-TOKEN'] = meta
  const res = await fetch(target.href, {
    method: 'POST',
    credentials: 'same-origin',
    headers,
    body: JSON.stringify(body),
    signal,
  })
  if (!res.ok) throw new Error(`${tr('Не удалось загрузить предпросмотр')}: HTTP ${res.status}`)
  return res.text()
}

/** Puts a `<base href>` into the page unless it has one: its relative addresses resolve against the site. */
export function withBase(html: string, base: string): string {
  if (/<base[\s>]/i.test(html)) return html
  const tag = `<base href="${base.replace(/"/g, '&quot;')}">`
  const head = /<head(\s[^>]*)?>/i.exec(html)
  if (head) return html.slice(0, head.index + head[0].length) + tag + html.slice(head.index + head[0].length)
  return tag + html
}

/**
 * The ref a message from the preview selects, or null: a `{type, ref}` of the
 * select type from the preview's own window and origin.
 */
export function refFromMessage(
  event: Pick<MessageEvent, 'data' | 'origin' | 'source'>,
  frame: Window | null | undefined,
  type = SELECT_MESSAGE,
): string | null {
  if (!frame || event.source !== frame) return null
  if (event.origin !== window.location.origin && event.origin !== 'null') return null
  const data = event.data as { type?: unknown; ref?: unknown } | null
  if (!data || typeof data !== 'object' || data.type !== type) return null
  if (typeof data.ref !== 'string' && typeof data.ref !== 'number') return null
  return String(data.ref)
}

/** The marker element of a ref in the preview's document. */
export function markerOf(doc: Document, attribute: string, ref: BlockRef): HTMLElement | null {
  const value = String(ref).replace(/\\/g, '\\\\').replace(/"/g, '\\"')
  return doc.querySelector<HTMLElement>(`[${attribute}="${value}"]`)
}

/** The element to outline and scroll to: a `display: contents` marker has no box of its own. */
export function boxOf(marker: HTMLElement): HTMLElement {
  const view = marker.ownerDocument.defaultView
  const display = view?.getComputedStyle ? view.getComputedStyle(marker).display : ''
  // Not `instanceof HTMLElement`: the page's elements belong to the frame's realm.
  const first = marker.firstElementChild as HTMLElement | null
  return display === 'contents' && first ? first : marker
}

export const ACTIVE_ATTRIBUTE = 'data-block-tree-active'

const STYLE_ID = 'admin-block-tree-preview-style'

/** The outline of the selected block and the hover hint, in the preview's document. */
export function ensurePreviewStyle(doc: Document): void {
  if (doc.getElementById(STYLE_ID)) return
  const style = doc.createElement('style')
  style.id = STYLE_ID
  style.textContent =
    `[${ACTIVE_ATTRIBUTE}]{outline:2px solid #2f80ed !important;outline-offset:2px}` +
    `[data-block-tree-hover]{outline:1px dashed #2f80ed !important;outline-offset:2px;cursor:pointer}`
  ;(doc.head ?? doc.documentElement).appendChild(style)
}

/** Outlines the block of `ref` (none — clears) and scrolls to it. */
export function highlight(doc: Document, attribute: string, ref: BlockRef | null, scroll = true): boolean {
  doc.querySelectorAll(`[${ACTIVE_ATTRIBUTE}]`).forEach((el) => el.removeAttribute(ACTIVE_ATTRIBUTE))
  if (ref === null) return false
  const marker = markerOf(doc, attribute, ref)
  if (!marker) return false
  const box = boxOf(marker)
  box.setAttribute(ACTIVE_ATTRIBUTE, '')
  if (scroll && typeof box.scrollIntoView === 'function') box.scrollIntoView({ block: 'center', behavior: 'smooth' })
  return true
}

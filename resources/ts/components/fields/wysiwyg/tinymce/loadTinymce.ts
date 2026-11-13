/**
 * Loads a self-hosted TinyMCE by its script URL — TinyMCE is not part of the
 * admin's bundle (its 7.x licence is GPL-2.0-or-later, the package is MIT), so
 * the host serves its own copy (tinymce.min.js with its skins, icons, models,
 * themes and plugins next to it) and names the URL in the field or in
 * config/admin.php. No cloud, no API key.
 *
 * The script is added once per URL; a TinyMCE already on the page
 * (window.tinymce) is used as is.
 */

/** The part of TinyMCE's global the driver uses. */
export interface TinymceEditorLike {
  getContent(): string
  setContent(html: string): void
  on(events: string, handler: () => void): void
  mode: { set(mode: 'design' | 'readonly'): void }
  remove(): void
}
export interface TinymceGlobal {
  init(options: Record<string, unknown>): Promise<TinymceEditorLike[]>
  majorVersion?: string
}

declare global {
  interface Window {
    tinymce?: TinymceGlobal
  }
}

const pending = new Map<string, Promise<TinymceGlobal>>()

/** The folder of the script: TinyMCE's base_url, where it looks for its skins and plugins. */
export function tinymceBaseUrl(script: string): string {
  const clean = script.split(/[?#]/)[0]
  return clean.slice(0, clean.lastIndexOf('/'))
}

export function loadTinymce(script: string | null | undefined): Promise<TinymceGlobal> {
  if (typeof window !== 'undefined' && window.tinymce) return Promise.resolve(window.tinymce)
  if (!script) return Promise.reject(new Error('TinyMCE script URL is not configured'))
  let promise = pending.get(script)
  if (!promise) {
    promise = new Promise<TinymceGlobal>((resolve, reject) => {
      const el = document.createElement('script')
      el.src = script
      el.async = true
      el.onload = () => (window.tinymce ? resolve(window.tinymce) : reject(new Error(`TinyMCE did not load from ${script}`)))
      el.onerror = () => reject(new Error(`TinyMCE did not load from ${script}`))
      document.head.appendChild(el)
    })
    // A failed download may succeed next time.
    promise.catch(() => pending.delete(script))
    pending.set(script, promise)
  }
  return promise
}

/** @internal For tests. */
export function resetTinymceLoader(): void {
  pending.clear()
}

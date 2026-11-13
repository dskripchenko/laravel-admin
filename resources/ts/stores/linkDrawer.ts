/**
 * The side panel a link opens over the current page (`target: 'drawer'`):
 * a resource's list — filtered and scoped as the link's query says — or a
 * screen. The page under it stays as it was; the panel's own list never
 * touches the address.
 *
 *   useLinkDrawerStore().open('/admin/r/claims?filter[phone]=79001234567', { title: 'Обращения' })
 *
 * AdminLinkDrawer (in the shell) draws it.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface LinkDrawerOptions {
  /** The panel's title; the list's or the screen's name when not given. */
  title?: string | null
  /** sm, md, lg, xl or a CSS length; xl by default. */
  size?: string | null
}

export const useLinkDrawerStore = defineStore('admin-link-drawer', () => {
  const url = ref<string | null>(null)
  const title = ref<string | null>(null)
  const size = ref<string | null>(null)
  /** Bumped on every open, so the same link opened again starts afresh. */
  const serial = ref(0)

  function open(href: string, options: LinkDrawerOptions = {}): void {
    url.value = href
    title.value = options.title ?? null
    size.value = options.size ?? null
    serial.value += 1
  }

  function close(): void {
    url.value = null
    title.value = null
    size.value = null
  }

  return { url, title, size, serial, open, close }
})

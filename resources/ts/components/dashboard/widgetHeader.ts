/**
 * The header links of a widget (Widget::headerLink(), 1.75) — a whole report,
 * a journal — handed from WidgetRenderer to the widget's header.
 *
 * WidgetRenderer provides them; a widget draws its header with WidgetHeader,
 * which shows them right of the title and claims them. A widget that draws
 * no WidgetHeader leaves them unclaimed, and WidgetRenderer then shows them
 * in the widget's top corner itself.
 */
import { inject, type InjectionKey, type Ref } from 'vue'

export interface WidgetHeaderLink {
  label: string
  url: string
  key?: string | null
}

export interface WidgetHeaderContext {
  links: Readonly<Ref<WidgetHeaderLink[]>>
  /** Set by a WidgetHeader that shows the links. */
  claimed: Ref<boolean>
}

export const WIDGET_HEADER_KEY: InjectionKey<WidgetHeaderContext> = Symbol('admin-widget-header')

/** The header links as the backend sent them; malformed entries dropped. */
export function normalizeHeaderLinks(raw: unknown): WidgetHeaderLink[] {
  if (!Array.isArray(raw)) return []
  return raw.filter((l): l is WidgetHeaderLink =>
    l !== null && typeof l === 'object'
      && typeof (l as WidgetHeaderLink).label === 'string'
      && typeof (l as WidgetHeaderLink).url === 'string'
      && (l as WidgetHeaderLink).url !== '')
}

export function useWidgetHeader(): WidgetHeaderContext | null {
  return inject(WIDGET_HEADER_KEY, null)
}

/** A link's kind: inside the panel (the router) or outside it (a new tab). */
export function isExternalUrl(url: string): boolean {
  return /^[a-z]+:\/\//i.test(url)
}

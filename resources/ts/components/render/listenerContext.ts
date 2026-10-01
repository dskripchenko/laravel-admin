/**
 * Where a Listener layout sends its requests.
 *
 * The page that owns the form provides it: ScreenPage points at the screen's
 * `listener` action, ResourceFormPage at the resource's, adding the form's
 * context and the record id. Without one — a settings page, a unit test — a
 * listener simply draws its children as the server first sent them.
 */

import { inject, provide, type InjectionKey } from 'vue'

export interface ListenerEndpoint {
  /** The action's URL relative to the API root, e.g. `/contact/listener`. */
  url: () => string
  /** Extra body fields sent along with `{listener, state}`. */
  extra?: () => Record<string, unknown>
}

const ListenerEndpointKey: InjectionKey<ListenerEndpoint> = Symbol('admin.listener-endpoint')

export function provideListenerEndpoint(endpoint: ListenerEndpoint): void {
  provide(ListenerEndpointKey, endpoint)
}

/** The endpoint, or null when no page provides one. */
export function useListenerEndpoint(): ListenerEndpoint | null {
  return inject(ListenerEndpointKey, null)
}

export { ListenerEndpointKey }

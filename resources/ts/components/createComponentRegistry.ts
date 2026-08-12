/**
 * The generic factory of the component registries: fields, layouts, widgets,
 * infolist entries and the rest.
 *
 * Every component registry in the admin is essentially the same thing — a
 * keyed Map<string, Component> plus register, get, has, list, clear and
 * registerBundle. This factory captures the pattern once, and the individual
 * modules merely rename the exports.
 *
 *     const reg = createComponentRegistry<Component>()
 *     export const registerWidget = reg.register
 *     export const getWidget = reg.get
 *     // ...
 */

export interface ComponentRegistry<T> {
  register(type: string, value: T): void
  get(type: string): T | null
  has(type: string): boolean
  list(): string[]
  clear(): void
  registerBundle(bundle: Record<string, T>): void
}

export function createComponentRegistry<T>(): ComponentRegistry<T> {
  const store = new Map<string, T>()

  return {
    register(type, value) {
      store.set(type, value)
    },
    get(type) {
      return store.get(type) ?? null
    },
    has(type) {
      return store.has(type)
    },
    list() {
      return [...store.keys()]
    },
    clear() {
      store.clear()
    },
    registerBundle(bundle) {
      for (const [type, value] of Object.entries(bundle)) {
        store.set(type, value)
      }
    },
  }
}

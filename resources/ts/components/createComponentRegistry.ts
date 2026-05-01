/**
 * Generic factory для component-registry'ов (fields, layouts, widgets,
 * infolist entries и т.п.).
 *
 * Все component-registry в admin'е по сути одинаковы: keyed Map<string, Component>
 * + register/get/has/list/clear/registerBundle. Этот factory вытаскивает паттерн
 * один раз; конкретные модули просто переименовывают экспорты.
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

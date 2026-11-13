/**
 * The WYSIWYG engines a Wysiwyg field can switch to with `->driver(...)`.
 *
 * The default editor (@dskripchenko/wysiwyg) is always there. Any other
 * engine is a loader registered under its name and called on first use, so a
 * page without such a field never downloads it:
 *
 *     registerWysiwygDriver('quill', () => import('./quill/QuillDriver.vue'))
 *
 * The prebuilt application registers Quill and TinyMCE this way
 * (builtinDrivers.ts). The library does not: `quill` is an optional peer
 * dependency, and a reference to it here would break the build of a host that
 * has not installed it. A host with its own build registers what it uses.
 *
 * A driver component receives `modelValue` (the stored string), `config` (the
 * field's `driverConfig`), `placeholder` and `disabled`, and emits
 * `update:modelValue`.
 */
import { defineAsyncComponent, h, type Component, type FunctionalComponent } from 'vue'
import { trSafe as tr } from '../../../stores/i18n'

export type WysiwygDriverLoader = () => Promise<Component | { default: Component }>

const loaders = new Map<string, WysiwygDriverLoader>()
const components = new Map<string, Component>()

/** Registers (or replaces) the loader of an engine. */
export function registerWysiwygDriver(name: string, loader: WysiwygDriverLoader): void {
  loaders.set(name, loader)
  components.delete(name)
}

export function hasWysiwygDriver(name: string): boolean {
  return loaders.has(name)
}

/** @internal Forgets every registered engine, for tests. */
export function clearWysiwygDrivers(): void {
  loaders.clear()
  components.clear()
}

const LoadError: FunctionalComponent = () =>
  h('p', { class: 'admin-field__error', role: 'alert' }, tr('Не удалось загрузить редактор.'))

const Loading: FunctionalComponent = () =>
  h('div', { class: 'admin-wysiwyg-driver__loading', 'aria-busy': 'true' }, tr('Загрузка редактора…'))

/**
 * The async component of an engine, made once per name; null when no such
 * engine is registered.
 */
export function resolveWysiwygDriver(name: string): Component | null {
  const cached = components.get(name)
  if (cached) return cached
  const loader = loaders.get(name)
  if (!loader) return null
  const component = defineAsyncComponent({
    loader,
    loadingComponent: Loading,
    errorComponent: LoadError,
    delay: 100,
  })
  components.set(name, component)
  return component
}

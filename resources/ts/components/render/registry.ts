/**
 * The registry of the Vue components behind the JSON-driven rendering.
 *
 * It holds two maps: one for the `field` types — the inputs and controls — and
 * one for the `layout` types, the containers. LayoutRenderer and FieldRenderer
 * resolve a type through them, and a host project registers its own components
 * with `registerField()` and `registerLayout()`.
 *
 * It is a singleton, one registry per admin instance. In tests the pattern is
 * `clearRegistry()` plus `registerField()` in a `beforeEach`.
 */

import type { Component } from 'vue'
import { createComponentRegistry } from '../createComponentRegistry'

const fields = createComponentRegistry<Component>()
const layouts = createComponentRegistry<Component>()

export const registerField = fields.register
export const getField = fields.get
export const hasField = fields.has
export const listFields = fields.list

export const registerLayout = layouts.register
export const getLayout = layouts.get
export const hasLayout = layouts.has
export const listLayouts = layouts.list

/** Clears both registries; used by the tests. */
export function clearRegistry(): void {
  fields.clear()
  layouts.clear()
}

/**
 * Registers several components at once.
 *
 *     registerComponents({
 *       fields: { text: TextField, select: SelectField },
 *       layouts: { rows: RowsLayout, tabs: TabsLayout },
 *     })
 */
export interface ComponentBundle {
  fields?: Record<string, Component>
  layouts?: Record<string, Component>
}

export function registerComponents(bundle: ComponentBundle): void {
  if (bundle.fields) fields.registerBundle(bundle.fields)
  if (bundle.layouts) layouts.registerBundle(bundle.layouts)
}

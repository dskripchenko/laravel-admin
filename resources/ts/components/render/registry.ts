/**
 * Реестр Vue-компонентов для JSON-driven рендеринга.
 *
 * Содержит две Map'ы — для `field`-типов (input'ы, контролы) и для
 * `layout`-типов (контейнеры). LayoutRenderer и FieldRenderer резолвят тип
 * через эти реестры; host-проект может зарегистрировать кастомные компоненты
 * через `registerField()` / `registerLayout()`.
 *
 * Singleton — один регистр на admin-instance. Тестовый сценарий —
 * `clearRegistry()` + `registerField()` в `beforeEach`.
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

/** Очистить оба реестра. Используется в тестах. */
export function clearRegistry(): void {
  fields.clear()
  layouts.clear()
}

/**
 * Зарегистрировать сразу несколько компонентов.
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

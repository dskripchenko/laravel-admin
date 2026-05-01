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

const fieldRegistry = new Map<string, Component>()
const layoutRegistry = new Map<string, Component>()

/** Зарегистрировать field-компонент. Перезаписывает, если уже есть. */
export function registerField(type: string, component: Component): void {
  fieldRegistry.set(type, component)
}

/** Зарегистрировать layout-компонент. */
export function registerLayout(type: string, component: Component): void {
  layoutRegistry.set(type, component)
}

/** Резолвит field-компонент или null. */
export function getField(type: string): Component | null {
  return fieldRegistry.get(type) ?? null
}

/** Резолвит layout-компонент или null. */
export function getLayout(type: string): Component | null {
  return layoutRegistry.get(type) ?? null
}

export function hasField(type: string): boolean {
  return fieldRegistry.has(type)
}

export function hasLayout(type: string): boolean {
  return layoutRegistry.has(type)
}

/** Snapshot всех зарегистрированных field-типов. */
export function listFields(): string[] {
  return [...fieldRegistry.keys()]
}

export function listLayouts(): string[] {
  return [...layoutRegistry.keys()]
}

/** Очистить оба реестра. Используется в тестах. */
export function clearRegistry(): void {
  fieldRegistry.clear()
  layoutRegistry.clear()
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
  if (bundle.fields) {
    for (const [type, component] of Object.entries(bundle.fields)) {
      registerField(type, component)
    }
  }
  if (bundle.layouts) {
    for (const [type, component] of Object.entries(bundle.layouts)) {
      registerLayout(type, component)
    }
  }
}

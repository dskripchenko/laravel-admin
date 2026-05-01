/**
 * Реестр dashboard widget-компонентов.
 *
 * Manifest узлы dashboard выглядят так:
 *   {
 *     slug: 'main',
 *     label: 'Главный',
 *     widgets: [
 *       { type: 'stat', name: 'total', title: 'Всего', value: 1284,
 *         span: 3, trend: 12 },
 *       { type: 'bar-chart', span: 8, ... },
 *       { type: 'recent-table', span: 8, columns: [...], rows: [...] },
 *     ]
 *   }
 *
 * Host регистрирует кастомные widget'ы через `registerWidget()` /
 * `registerWidgets()` bundle.
 */

import type { Component } from 'vue'

const widgetRegistry = new Map<string, Component>()

export function registerWidget(type: string, component: Component): void {
  widgetRegistry.set(type, component)
}

export function getWidget(type: string): Component | null {
  return widgetRegistry.get(type) ?? null
}

export function hasWidget(type: string): boolean {
  return widgetRegistry.has(type)
}

export function listWidgets(): string[] {
  return [...widgetRegistry.keys()]
}

export function clearWidgetRegistry(): void {
  widgetRegistry.clear()
}

export function registerWidgets(bundle: Record<string, Component>): void {
  for (const [type, component] of Object.entries(bundle)) {
    widgetRegistry.set(type, component)
  }
}

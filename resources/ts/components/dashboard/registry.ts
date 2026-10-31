/**
 * The registry of the dashboard widget components and of their descriptions.
 *
 * A dashboard's manifest nodes look like this:
 *   {
 *     slug: 'main',
 *     label: 'Main',
 *     widgets: [
 *       { type: 'stat', name: 'total', title: 'Total', value: 1284,
 *         span: 3, trend: 12 },
 *       { type: 'bar-chart', span: 8, ... },
 *       { type: 'recent-table', span: 8, columns: [...], rows: [...] },
 *     ]
 *   }
 *
 * A host registers its own widgets through `registerWidget()` or a
 * `registerWidgets()` bundle. A widget type a user may add on the dashboard
 * also gets a description — its label, icon, default size and config form,
 * see widgetConfig.ts — passed as registerWidget()'s third argument or
 * through `describeWidget()`.
 */

import type { Component } from 'vue'
import { createComponentRegistry } from '../createComponentRegistry'
import type { WidgetTypeDescription } from './widgetConfig'

const widgets = createComponentRegistry<Component>()
const descriptions = new Map<string, WidgetTypeDescription>()

/**
 * Registers the component that draws a widget type and, optionally, its
 * description for the add and configure dialogs.
 */
export function registerWidget(type: string, component: Component, description?: WidgetTypeDescription): void {
  widgets.register(type, component)
  if (description) descriptions.set(type, description)
}

/**
 * Describes a widget type — a built-in one or one registered before — for
 * the add and configure dialogs: the label, the icon, whether a user may add
 * it, the default size and the config form. A later call replaces the
 * description; several types (aliases) may share one.
 */
export function describeWidget(type: string | string[], description: WidgetTypeDescription): void {
  for (const t of Array.isArray(type) ? type : [type]) descriptions.set(t, description)
}

export function getWidgetDescription(type: string): WidgetTypeDescription | null {
  return descriptions.get(type) ?? null
}

export function hasWidgetDescription(type: string): boolean {
  return descriptions.has(type)
}

export const getWidget = widgets.get
export const hasWidget = widgets.has
export const listWidgets = widgets.list
export const registerWidgets = widgets.registerBundle

/**
 * The widget types a user may add: every registered component whose
 * description does not say `addable: false`. A component without a
 * description is offered under its type name, as before descriptions existed.
 */
export function listAddableWidgets(): string[] {
  return widgets.list().filter((type) => descriptions.get(type)?.addable !== false)
}

/** Forgets every widget component and description. */
export function clearWidgetRegistry(): void {
  widgets.clear()
  descriptions.clear()
}

/**
 * The registry of the dashboard widget components.
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
 * `registerWidgets()` bundle.
 */

import type { Component } from 'vue'
import { createComponentRegistry } from '../createComponentRegistry'

const widgets = createComponentRegistry<Component>()

export const registerWidget = widgets.register
export const getWidget = widgets.get
export const hasWidget = widgets.has
export const listWidgets = widgets.list
export const clearWidgetRegistry = widgets.clear
export const registerWidgets = widgets.registerBundle

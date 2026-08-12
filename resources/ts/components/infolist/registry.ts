/**
 * The registry of the infolist entry components.
 *
 * It mirrors the field registry, but for a read-only display. The manifest's
 * nodes look like:
 *   { type: 'text', name: 'title', label: 'Title', value?: '...' }
 * or come without an explicit `value`, in which case the entry takes
 * `record[name]` from the provided `record`.
 *
 * A host registers its own entries through `registerInfolistEntry()` or a
 * `registerInfolistEntries()` bundle.
 */

import type { Component } from 'vue'
import { createComponentRegistry } from '../createComponentRegistry'

const entries = createComponentRegistry<Component>()

export const registerInfolistEntry = entries.register
export const getInfolistEntry = entries.get
export const hasInfolistEntry = entries.has
export const listInfolistEntries = entries.list
export const clearInfolistRegistry = entries.clear
export const registerInfolistEntries = entries.registerBundle

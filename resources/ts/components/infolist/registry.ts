/**
 * Реестр infolist-entry-компонентов.
 *
 * Аналог field-registry, но для read-only display'я. Узлы манифеста имеют
 * форму:
 *   { type: 'text', name: 'title', label: 'Заголовок', value?: '...' }
 * либо без явного `value` — тогда entry извлекает `record[name]` из
 * provide'нутого `record`.
 *
 * Host регистрирует кастомные entry через `registerInfolistEntry()` /
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

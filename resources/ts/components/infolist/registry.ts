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

const entryRegistry = new Map<string, Component>()

export function registerInfolistEntry(type: string, component: Component): void {
  entryRegistry.set(type, component)
}

export function getInfolistEntry(type: string): Component | null {
  return entryRegistry.get(type) ?? null
}

export function hasInfolistEntry(type: string): boolean {
  return entryRegistry.has(type)
}

export function listInfolistEntries(): string[] {
  return [...entryRegistry.keys()]
}

export function clearInfolistRegistry(): void {
  entryRegistry.clear()
}

export function registerInfolistEntries(bundle: Record<string, Component>): void {
  for (const [type, component] of Object.entries(bundle)) {
    entryRegistry.set(type, component)
  }
}

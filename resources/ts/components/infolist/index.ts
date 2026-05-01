/**
 * Public exports infolist-системы.
 */

export { default as InfolistRenderer } from './InfolistRenderer.vue'
export type { InfolistNode } from './InfolistRenderer.vue'

export { default as TextEntry } from './TextEntry.vue'
export { default as BadgeEntry } from './BadgeEntry.vue'
export { default as IconEntry } from './IconEntry.vue'
export { default as KeyValueEntry } from './KeyValueEntry.vue'
export { default as UnknownEntry } from './UnknownEntry.vue'

export {
  registerInfolistEntry,
  registerInfolistEntries,
  getInfolistEntry,
  hasInfolistEntry,
  listInfolistEntries,
  clearInfolistRegistry,
} from './registry'

export { registerBuiltinInfolistEntries } from './builtin'

export {
  provideRecord,
  useRecord,
  tryUseRecord,
} from './recordContext'

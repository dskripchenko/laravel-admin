/**
 * Default-bundle с минимальным набором builtin infolist-entry'ев.
 */

import { registerInfolistEntries } from './registry'
import TextEntry from './TextEntry.vue'
import BadgeEntry from './BadgeEntry.vue'
import IconEntry from './IconEntry.vue'
import KeyValueEntry from './KeyValueEntry.vue'

export function registerBuiltinInfolistEntries(): void {
  registerInfolistEntries({
    text: TextEntry,
    badge: BadgeEntry,
    icon: IconEntry,
    keyvalue: KeyValueEntry,
    'key-value': KeyValueEntry,
  })
}

/**
 * The default bundle, with the minimal set of built-in infolist entries.
 *
 * The view page (ResourceViewPage) uses the same type strings as the form page
 * (ResourceFormPage): the backend's Field::fieldType() returns one name in
 * both contexts. So the infolist mapping has to cover the same types
 * registerBuiltinComponents does, falling back to TextEntry for every
 * read-only rendering.
 */

import { registerInfolistEntries } from './registry'
import TextEntry from './TextEntry.vue'
import BadgeEntry from './BadgeEntry.vue'
import IconEntry from './IconEntry.vue'
import KeyValueEntry from './KeyValueEntry.vue'
import RepeatableEntry from './RepeatableEntry.vue'

export function registerBuiltinInfolistEntries(): void {
  registerInfolistEntries({
    // The infolist's own types: BadgeEntry::make() and the rest.
    text: TextEntry,
    badge: BadgeEntry,
    icon: IconEntry,
    keyvalue: KeyValueEntry,
    key_value: KeyValueEntry,
    'key-value': KeyValueEntry,
    // Repeatable: a collection of objects with nested entries — a table, cards or inline.
    repeatable: RepeatableEntry,
    // The mapping from the backend's Field::fieldType() to TextEntry for the
    // view mode. A host may override it with
    // registerInfolistEntry('wysiwyg', WysiwygEntry).
    input: TextEntry,
    email: TextEntry,
    url: TextEntry,
    password: TextEntry,
    tel: TextEntry,
    search: TextEntry,
    slug: TextEntry,
    hidden: TextEntry,
    label: TextEntry,
    textarea: TextEntry,
    wysiwyg: TextEntry,
    markdown: TextEntry,
    code: TextEntry,
    number: TextEntry,
    slider: TextEntry,
    rating: TextEntry,
    select: TextEntry,
    combobox: TextEntry,
    radio: TextEntry,
    tags: TextEntry,
    'morph-switcher': TextEntry,
    relation: TextEntry,
    cascader: TextEntry,
    'tree-select': TextEntry,
    checkbox: TextEntry,
    switch: TextEntry,
    switcher: TextEntry,
    boolean: TextEntry,
    date: TextEntry,
    datetime: TextEntry,
    datepicker: TextEntry,
    'date-range': TextEntry,
    time: TextEntry,
    'time-picker': TextEntry,
    'color-picker': TextEntry,
  })
}

/**
 * Default-bundle с минимальным набором builtin infolist-entry'ев.
 *
 * View-страница (ResourceViewPage) использует те же type-strings что и
 * form-page (ResourceFormPage) — backend Field::fieldType() возвращает
 * одинаковое имя в обоих контекстах. Поэтому infolist-mapping должен
 * покрывать те же типы что registerBuiltinComponents — fallback на
 * TextEntry для всех stdread-only представлений.
 */

import { registerInfolistEntries } from './registry'
import TextEntry from './TextEntry.vue'
import BadgeEntry from './BadgeEntry.vue'
import IconEntry from './IconEntry.vue'
import KeyValueEntry from './KeyValueEntry.vue'

export function registerBuiltinInfolistEntries(): void {
  registerInfolistEntries({
    // Дефолтные типы infolist (BadgeEntry::make() и т.п.).
    text: TextEntry,
    badge: BadgeEntry,
    icon: IconEntry,
    keyvalue: KeyValueEntry,
    key_value: KeyValueEntry,
    'key-value': KeyValueEntry,
    // Repeatable: fallback на TextEntry с json-preset
    // (полная реализация с nested-entries — на будущее).
    repeatable: TextEntry,
    // Маппинг от backend Field::fieldType() → TextEntry для view-режима.
    // Host'ы могут перебить registerInfolistEntry('wysiwyg', WysiwygEntry).
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

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
import ColorEntry from './ColorEntry.vue'
import ImageEntry from './ImageEntry.vue'
import MapEntry from './MapEntry.vue'
import RelationEntry from './RelationEntry.vue'
import FieldEntry from './FieldEntry.vue'
import MarkdownEntry from './MarkdownEntry.vue'
import CodeEntry from './CodeEntry.vue'
import RatingEntry from './RatingEntry.vue'
import OptionEntry from './OptionEntry.vue'
import TreeSelectEntry from './TreeSelectEntry.vue'
import CascaderEntry from './CascaderEntry.vue'
import DateRangeEntry from './DateRangeEntry.vue'
import MorphEntry from './MorphEntry.vue'
import GroupEntry from './GroupEntry.vue'
import HiddenEntry from './HiddenEntry.vue'
import ResourcePickerEntry from './ResourcePickerEntry.vue'

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
    color: ColorEntry,
    image: ImageEntry,
    map: MapEntry,
    relation: RelationEntry,
    // A serialized form field, drawn by the entry registered under its type.
    field: FieldEntry,
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
    hidden: HiddenEntry,
    label: TextEntry,
    textarea: TextEntry,
    wysiwyg: TextEntry,
    markdown: MarkdownEntry,
    code: CodeEntry,
    number: TextEntry,
    slider: TextEntry,
    rating: RatingEntry,
    // The option's label, not its stored key.
    select: OptionEntry,
    combobox: OptionEntry,
    radio: OptionEntry,
    tags: OptionEntry,
    relation_select: OptionEntry,
    morph_switcher: MorphEntry,
    'morph-switcher': MorphEntry,
    cascader: CascaderEntry,
    tree_select: TreeSelectEntry,
    'tree-select': TreeSelectEntry,
    group: GroupEntry,
    checkbox: TextEntry,
    switch: TextEntry,
    switcher: TextEntry,
    boolean: TextEntry,
    date: TextEntry,
    datetime: TextEntry,
    datepicker: TextEntry,
    date_range: DateRangeEntry,
    'date-range': DateRangeEntry,
    time: TextEntry,
    'time-picker': TextEntry,
    'color-picker': ColorEntry,
    resource_picker: ResourcePickerEntry,
  })
}

/**
 * Default-bundle с минимальным набором builtin-компонентов.
 *
 * Использование:
 *
 *     import { registerBuiltinComponents } from '@dskripchenko/laravel-admin'
 *     registerBuiltinComponents()
 */

import { registerComponents } from './registry'
import TextField from '../fields/TextField.vue'
import TextAreaField from '../fields/TextAreaField.vue'
import NumberField from '../fields/NumberField.vue'
import SelectField from '../fields/SelectField.vue'
import CheckboxField from '../fields/CheckboxField.vue'
import DateField from '../fields/DateField.vue'
import TagsField from '../fields/TagsField.vue'
import TranslatableField from '../fields/TranslatableField.vue'
import WysiwygField from '../fields/WysiwygField.vue'
import FileField from '../fields/FileField.vue'
import ImageCropperField from '../fields/ImageCropperField.vue'
import RowsLayout from '../layouts/RowsLayout.vue'
import ColumnsLayout from '../layouts/ColumnsLayout.vue'
import SectionLayout from '../layouts/SectionLayout.vue'
import TabsLayout from '../layouts/TabsLayout.vue'
import EmbeddedResourceTable from '../layouts/EmbeddedResourceTable.vue'

export function registerBuiltinComponents(): void {
  registerComponents({
    fields: {
      // Backend Field-классы из dskripchenko/laravel-admin отдают эти fieldType()
      // строки. Соответствие см. core/src/Field/{Input,TextArea,Select,...}.php.
      input: TextField,
      text: TextField,
      email: TextField,
      url: TextField,
      password: TextField,
      tel: TextField,
      search: TextField,
      slug: TextField,
      hidden: TextField,
      label: TextField,
      textarea: TextAreaField,
      // WYSIWYG default — собственный @dskripchenko/wysiwyg (zero-dep,
      // ~7 KB gzip). Host может перебить:
      //   import { QuillField } from '@dskripchenko/laravel-admin/quill'
      //   registerField('wysiwyg', QuillField)
      wysiwyg: WysiwygField,
      markdown: TextAreaField,
      code: TextAreaField,
      number: NumberField,
      slider: NumberField,
      rating: NumberField,
      select: SelectField,
      combobox: SelectField,
      radio: SelectField,
      tags: TagsField,
      // Translatable: backend Field\TranslatableInput → fieldType()='translatable'.
      // Хранит value как Record<locale, string>; UI показывает табы по локалям.
      translatable: TranslatableField,
      'translatable-text': TranslatableField,
      'morph-switcher': SelectField,
      relation: SelectField,
      cascader: SelectField,
      'tree-select': SelectField,
      checkbox: CheckboxField,
      switch: CheckboxField,
      switcher: CheckboxField,
      boolean: CheckboxField,
      date: DateField,
      datetime: DateField,
      datepicker: DateField,
      'date-range': DateField,
      time: DateField,
      'time-picker': DateField,
      'color-picker': TextField,
      file: FileField,
      image: FileField,
      image_cropper: ImageCropperField,
    },
    layouts: {
      rows: RowsLayout,
      columns: ColumnsLayout,
      section: SectionLayout,
      block: SectionLayout,
      tabs: TabsLayout,
      accordion: SectionLayout,
      group: RowsLayout,
      step: SectionLayout,
      wizard: SectionLayout,
      'admin.resource-table': EmbeddedResourceTable,
    },
  })
}

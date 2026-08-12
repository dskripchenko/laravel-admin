/**
 * The default bundle, with the minimal set of built-in components.
 *
 * Usage:
 *
 *     import { registerBuiltinComponents } from '@dskripchenko/laravel-admin'
 *     registerBuiltinComponents()
 */

import { defineComponent, h } from 'vue'
import { hasField, hasLayout, registerField, registerLayout } from './registry'
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
import KeyValueField from '../fields/KeyValueField.vue'
import RepeaterField from '../fields/RepeaterField.vue'
import BuilderField from '../fields/BuilderField.vue'
import RelationTableField from '../fields/RelationTableField.vue'
import GeneratedField from '../fields/GeneratedField.vue'
import RowsLayout from '../layouts/RowsLayout.vue'
import ColumnsLayout from '../layouts/ColumnsLayout.vue'
import SectionLayout from '../layouts/SectionLayout.vue'
import TabsLayout from '../layouts/TabsLayout.vue'
import EmbeddedResourceTable from '../layouts/EmbeddedResourceTable.vue'
import DashboardLayout from '../layouts/DashboardLayout.vue'

/**
 * A TextField with the input's `type` preset.
 *
 * The backend's `password`, `email`, `url`, `tel` and `search` fields were all
 * rendered by the same TextField, which defaults to `type="text"` — so
 * `Password::make()` showed the secret in the clear, and the mobile keyboard
 * did not adjust for an email or a phone number. The type now comes from the
 * registry key; an explicit `inputType` in the field's attributes still wins.
 */
type TextInputType = 'text' | 'email' | 'url' | 'password' | 'tel' | 'search'

function textFieldOfType(inputType: TextInputType) {
  return defineComponent({
    name: `TextField${inputType.charAt(0).toUpperCase()}${inputType.slice(1)}`,
    inheritAttrs: false,
    setup(_props, { attrs }) {
      // The props arrive through attrs, since the component does not declare
      // them, so h() cannot infer the type — we narrow it explicitly.
      const props = { ...attrs, inputType: (attrs.inputType as TextInputType | undefined) ?? inputType }

      return () => h(TextField, props as unknown as InstanceType<typeof TextField>['$props'])
    },
  })
}

/**
 * The built-in components do NOT override what the host registered:
 * a registerField(...) before createAdminApp() wins.
 */
function registerAbsent(
  bundle: { fields?: Record<string, unknown>; layouts?: Record<string, unknown> },
): void {
  for (const [k, v] of Object.entries(bundle.fields ?? {})) {
    if (!hasField(k)) registerField(k, v as never)
  }
  for (const [k, v] of Object.entries(bundle.layouts ?? {})) {
    if (!hasLayout(k)) registerLayout(k, v as never)
  }
}

export function registerBuiltinComponents(): void {
  registerAbsent({
    fields: {
      // The backend field classes of dskripchenko/laravel-admin return these
      // fieldType() strings; see core/src/Field/{Input,TextArea,Select,...}.php.
      input: TextField,
      text: TextField,
      email: textFieldOfType('email'),
      url: textFieldOfType('url'),
      password: textFieldOfType('password'),
      tel: textFieldOfType('tel'),
      search: textFieldOfType('search'),
      slug: TextField,
      hidden: TextField,
      label: TextField,
      textarea: TextAreaField,
      // The default WYSIWYG is our own @dskripchenko/wysiwyg: no
      // dependencies, about 7 KB gzipped. A host may override it:
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
      // Translatable: the backend's Field\TranslatableInput has
      // fieldType()='translatable'. The value is a Record<locale, string>, and
      // the UI shows a tab per locale.
      translatable: TranslatableField,
      'translatable-text': TranslatableField,
      // The backend's fieldType() returns snake_case, so that is what we
      // register; the dashed variants remain as historical aliases for host
      // code.
      morph_switcher: SelectField,
      'morph-switcher': SelectField,
      relation_select: SelectField,
      relation: SelectField,
      cascader: SelectField,
      tree_select: SelectField,
      'tree-select': SelectField,
      checkbox: CheckboxField,
      switch: CheckboxField,
      switcher: CheckboxField,
      boolean: CheckboxField,
      date: DateField,
      datetime: DateField,
      datepicker: DateField,
      date_range: DateField,
      'date-range': DateField,
      time: DateField,
      'time-picker': DateField,
      color: TextField,
      'color-picker': TextField,
      file: FileField,
      image: FileField,
      image_cropper: ImageCropperField,
      // The composite fields, which used to be drawn by UnknownField.
      key_value: KeyValueField,
      repeater: RepeaterField,
    'generated-field': GeneratedField,
      builder: BuilderField,
      relation_table: RelationTableField,
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
      // Widgets on an ordinary screen: `Layout\Dashboard::make([...])`.
      dashboard: DashboardLayout,
      'admin.resource-table': EmbeddedResourceTable,
    },
  })
}

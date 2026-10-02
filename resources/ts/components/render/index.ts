/**
 * The public exports of the JSON-driven rendering.
 */

export { default as FieldRenderer } from './FieldRenderer.vue'
export type { FieldNode } from './FieldRenderer.vue'
export { default as LayoutRenderer } from './LayoutRenderer.vue'
export type { LayoutNode } from './LayoutRenderer.vue'

export {
  registerField,
  registerLayout,
  getField,
  getLayout,
  hasField,
  hasLayout,
  listFields,
  listLayouts,
  clearRegistry,
  registerComponents,
} from './registry'
export type { ComponentBundle } from './registry'

export { registerBuiltinComponents } from './builtin'

export {
  provideFormState,
  useFormState,
  tryUseFormState,
} from './formState'
export type { FormStateContext } from './formState'

export {
  createScreenContext,
  provideScreenContext,
  useScreenContext,
} from './screenContext'
export type { ScreenContext, ScreenContextOptions, ScreenActionLike } from './screenContext'

export { provideListenerEndpoint, useListenerEndpoint } from './listenerContext'
export type { ListenerEndpoint } from './listenerContext'

// The built-in field and layout SFCs are re-exported so a host project can wrap or extend them.
export { default as TextField } from '../fields/TextField.vue'
export { default as TextAreaField } from '../fields/TextAreaField.vue'
export { default as NumberField } from '../fields/NumberField.vue'
export { default as SelectField } from '../fields/SelectField.vue'
export { default as ComboboxField } from '../fields/ComboboxField.vue'
export { default as CheckboxField } from '../fields/CheckboxField.vue'
export { default as DateField } from '../fields/DateField.vue'
export { default as UnknownField } from '../fields/UnknownField.vue'
export { default as RowsLayout } from '../layouts/RowsLayout.vue'
export { default as ColumnsLayout } from '../layouts/ColumnsLayout.vue'
export { default as SectionLayout } from '../layouts/SectionLayout.vue'
export { default as TabsLayout } from '../layouts/TabsLayout.vue'
export { default as AccordionLayout } from '../layouts/AccordionLayout.vue'
export { default as WizardLayout } from '../layouts/WizardLayout.vue'
export { default as ModalLayout } from '../layouts/ModalLayout.vue'
export { default as DrawerLayout } from '../layouts/DrawerLayout.vue'
export { default as ViewLayout } from '../layouts/ViewLayout.vue'
export { default as WrapperLayout } from '../layouts/WrapperLayout.vue'
export { default as InfolistLayout } from '../layouts/InfolistLayout.vue'
export { default as AuditTrailLayout } from '../layouts/AuditTrailLayout.vue'
export { default as MarkdownLayout } from '../layouts/MarkdownLayout.vue'
export { default as CodeLayout } from '../layouts/CodeLayout.vue'
export { default as ListenerLayout } from '../layouts/ListenerLayout.vue'

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
export type { ScreenContext, ScreenContextOptions, ScreenActionLike, ScreenMethodCaller } from './screenContext'

export { provideListenerEndpoint, useListenerEndpoint } from './listenerContext'
export type { ListenerEndpoint } from './listenerContext'

// The built-in field and layout SFCs are re-exported so a host project can wrap or extend them.
export { default as TextField } from '../fields/TextField.vue'
export { default as SlugField } from '../fields/SlugField.vue'
export { slugify } from '../fields/support/slugify'
export { default as TextAreaField } from '../fields/TextAreaField.vue'
export { default as NumberField } from '../fields/NumberField.vue'
export { default as SelectField } from '../fields/SelectField.vue'
export { default as ComboboxField } from '../fields/ComboboxField.vue'
export { default as CheckboxField } from '../fields/CheckboxField.vue'
export { default as SwitchField } from '../fields/SwitchField.vue'
export { default as DateField } from '../fields/DateField.vue'
export { default as UnknownField } from '../fields/UnknownField.vue'
export { default as FlowField } from '../fields/FlowField.vue'
export { default as BpmnField } from '../fields/BpmnField.vue'
export { default as DmnField } from '../fields/DmnField.vue'
export { default as KanbanField } from '../fields/KanbanField.vue'
export { default as CalendarField } from '../fields/CalendarField.vue'
export { default as MarkdownEditorField } from '../fields/MarkdownEditorField.vue'
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
export { default as FlowLayout } from '../layouts/FlowLayout.vue'
export { default as VideoLayout } from '../layouts/VideoLayout.vue'
export { default as AudioLayout } from '../layouts/AudioLayout.vue'
export { default as DiagramLayout } from '../layouts/DiagramLayout.vue'
export { default as BpmnLayout } from '../layouts/BpmnLayout.vue'
export { default as DmnLayout } from '../layouts/DmnLayout.vue'
export { default as DrawioLayout } from '../layouts/DrawioLayout.vue'
export { default as AsyncApiLayout } from '../layouts/AsyncApiLayout.vue'
export { default as KanbanLayout } from '../layouts/KanbanLayout.vue'
export { default as CalendarLayout } from '../layouts/CalendarLayout.vue'
